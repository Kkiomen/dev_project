<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SocialPostResource;
use App\Models\PostMedia;
use App\Models\SocialPost;
use App\Services\Webhook\WebhookDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostAutomationController extends Controller
{
    public function __construct(
        protected WebhookDispatchService $webhookService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SocialPost::forUser($request->user())
            ->with(['platformPosts', 'media', 'brand'])
            ->withCount('media');

        if ($request->has('brand_id') && $request->brand_id) {
            $query->whereHas('brand', function ($q) use ($request) {
                $q->where('public_id', $request->brand_id);
            });
        }

        if ($request->has('status') && $request->status) {
            $status = PostStatus::tryFrom($request->status);
            if ($status) {
                $query->withStatus($status);
            }
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('main_caption', 'like', "%{$search}%");
            });
        }

        $posts = $query->latest()->paginate($request->get('per_page', 20));

        return SocialPostResource::collection($posts);
    }

    public function generateText(Request $request, SocialPost $post): JsonResponse
    {
        $this->authorize('update', $post);

        $request->validate([
            'prompt' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->webhookService->generateText($post, $request->prompt);

        if ($result['success']) {
            // No webhook configured - just return success (nothing to do)
            if (!empty($result['skipped'])) {
                return response()->json([
                    'success' => true,
                    'skipped' => true,
                    'message' => $result['message'] ?? 'No text generation webhook configured',
                ]);
            }

            // Async mode: n8n trigger accepted the request, result will come via callback
            if (!empty($result['async'])) {
                return response()->json([
                    'success' => true,
                    'async' => true,
                    'message' => $result['message'] ?? 'Processing in background',
                ], 202);
            }

            // Synchronous mode: result is immediately available
            $updateData = [];
            if (!empty($result['caption'])) {
                $updateData['main_caption'] = $result['caption'];
            }
            if (!empty($result['title'])) {
                $updateData['title'] = $result['title'];
            }
            if (!empty($updateData)) {
                $post->update($updateData);
            }

            return response()->json([
                'success' => true,
                'data' => new SocialPostResource($post->fresh()->load(['platformPosts', 'media', 'brand'])),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Text generation failed',
        ], 422);
    }

    public function generateImagePrompt(Request $request, SocialPost $post): JsonResponse
    {
        $this->authorize('update', $post);

        $result = $this->webhookService->generateImagePrompt($post);

        if ($result['success']) {
            // No webhook configured - just return success (nothing to do)
            if (!empty($result['skipped'])) {
                return response()->json([
                    'success' => true,
                    'skipped' => true,
                    'message' => $result['message'] ?? 'No image generation webhook configured',
                ]);
            }

            // Async mode: n8n trigger accepted the request, result will come via callback
            if (!empty($result['async'])) {
                return response()->json([
                    'success' => true,
                    'async' => true,
                    'message' => $result['message'] ?? 'Processing in background',
                ], 202);
            }

            // Synchronous mode: result is immediately available
            // Handle base64 image from webhook
            if (!empty($result['image_base64'])) {
                $this->saveBase64Image($post, $result['image_base64'], $result['filename'] ?? null);
            }

            // Still support image_prompt text if returned alongside image
            if (!empty($result['image_prompt'])) {
                $post->update(['image_prompt' => $result['image_prompt']]);
            }

            return response()->json([
                'success' => true,
                'data' => new SocialPostResource($post->fresh()->load(['platformPosts', 'media', 'brand'])),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Image generation failed',
        ], 422);
    }

    public function webhookPublish(Request $request, SocialPost $post): JsonResponse
    {
        $this->authorize('update', $post);

        if ($post->status !== PostStatus::Approved && $post->status !== PostStatus::Scheduled) {
            return response()->json([
                'success' => false,
                'error' => 'Post must be approved before publishing',
            ], 400);
        }

        $result = $this->webhookService->publish($post);

        if ($result['success']) {
            // No webhook configured - just return success (nothing to do)
            if (!empty($result['skipped'])) {
                return response()->json([
                    'success' => true,
                    'skipped' => true,
                    'message' => $result['message'] ?? 'No publish webhook configured',
                ]);
            }

            // Async mode: n8n trigger accepted the request, result will come via callback
            if (!empty($result['async'])) {
                return response()->json([
                    'success' => true,
                    'async' => true,
                    'message' => $result['message'] ?? 'Publishing in background',
                ], 202);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'] ?? 'Post sent for publishing',
                'data' => new SocialPostResource($post->fresh()->load(['platformPosts', 'media', 'brand'])),
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'] ?? 'Publishing failed',
        ], 422);
    }

    public function bulkGenerateText(Request $request): JsonResponse
    {
        $request->validate([
            'post_ids' => ['required', 'array', 'min:1', 'max:20'],
            'post_ids.*' => ['string'],
        ]);

        $posts = SocialPost::forUser($request->user())
            ->whereIn('public_id', $request->post_ids)
            ->with('brand')
            ->get();

        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($posts as $post) {
            $result = $this->webhookService->generateText($post);

            if ($result['success']) {
                $updateData = [];
                if (!empty($result['caption'])) {
                    $updateData['main_caption'] = $result['caption'];
                }
                if (!empty($result['title'])) {
                    $updateData['title'] = $result['title'];
                }
                if (!empty($updateData)) {
                    $post->update($updateData);
                }
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'post_id' => $post->public_id,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }
        }

        return response()->json($results);
    }

    public function bulkGenerateImagePrompt(Request $request): JsonResponse
    {
        $request->validate([
            'post_ids' => ['required', 'array', 'min:1', 'max:20'],
            'post_ids.*' => ['string'],
        ]);

        $posts = SocialPost::forUser($request->user())
            ->whereIn('public_id', $request->post_ids)
            ->with('brand')
            ->get();

        $results = ['success' => 0, 'failed' => 0, 'errors' => []];

        foreach ($posts as $post) {
            $result = $this->webhookService->generateImagePrompt($post);

            if ($result['success']) {
                if (!empty($result['image_base64'])) {
                    $this->saveBase64Image($post, $result['image_base64'], $result['filename'] ?? null);
                }
                if (!empty($result['image_prompt'])) {
                    $post->update(['image_prompt' => $result['image_prompt']]);
                }
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'post_id' => $post->public_id,
                    'error' => $result['error'] ?? 'Unknown error',
                ];
            }
        }

        return response()->json($results);
    }

    protected function saveBase64Image(SocialPost $post, string $base64, ?string $filename = null): PostMedia
    {
        // Strip data URI prefix if present (e.g. "data:image/png;base64,...")
        if (str_contains($base64, ',')) {
            $base64 = substr($base64, strpos($base64, ',') + 1);
        }

        $imageData = base64_decode($base64);
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);

        $extension = match ($mimeType) {
            'image/png' => 'png',
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };

        $filename = $filename ?: ('generated-' . Str::random(12) . '.' . $extension);
        $path = 'post-media/' . $post->id . '/' . Str::random(16) . '.' . $extension;

        Storage::disk('public')->put($path, $imageData);

        // Get image dimensions
        $imageSize = @getimagesizefromstring($imageData);
        $width = $imageSize[0] ?? null;
        $height = $imageSize[1] ?? null;

        return $post->media()->create([
            'type' => 'image',
            'filename' => $filename,
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $mimeType,
            'size' => strlen($imageData),
            'width' => $width,
            'height' => $height,
        ]);
    }
}
