<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SocialPost;
use App\Services\Publishing\PublishingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function __construct(
        protected PublishingService $publishingService
    ) {}

    /**
     * Handle publishing result callback from n8n.
     */
    public function publishResult(Request $request): JsonResponse
    {
        // Validate webhook secret if configured
        $secret = config('services.n8n.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            Log::warning('Invalid webhook secret', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid webhook secret'], 403);
        }

        $request->validate([
            'post_id' => ['required', 'string'],
            'platform' => ['required', 'string', 'in:facebook,instagram,youtube'],
            'success' => ['required', 'boolean'],
            'external_id' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
        ]);

        $result = $this->publishingService->handleCallback($request->all());

        if (!$result['success']) {
            return response()->json($result, 400);
        }

        return response()->json($result);
    }

    /**
     * Health check endpoint for n8n.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'publishing-webhook',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Handle automation callback from n8n trigger workflows.
     * Called by AiselloRespond node after async processing.
     */
    public function automationCallback(Request $request): JsonResponse
    {
        // Validate webhook secret if configured
        $secret = config('services.n8n.webhook_secret');
        if ($secret && $request->header('X-Webhook-Secret') !== $secret) {
            Log::warning('Invalid webhook secret for automation callback', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid webhook secret'], 403);
        }

        $request->validate([
            'post_id' => ['required', 'string'],
            'type' => ['required', 'string', 'in:text_generation,image_generation,publish'],
            'success' => ['required', 'boolean'],
            'caption' => ['nullable', 'string'],
            'title' => ['nullable', 'string'],
            'image_prompt' => ['nullable', 'string'],
            'image_base64' => ['nullable', 'string'],
            'platform' => ['nullable', 'string'],
            'external_id' => ['nullable', 'string'],
            'error' => ['nullable', 'string'],
        ]);

        $post = SocialPost::where('public_id', $request->post_id)->first();

        if (!$post) {
            Log::warning('Automation callback: Post not found', [
                'post_id' => $request->post_id,
            ]);
            return response()->json(['error' => 'Post not found'], 404);
        }

        if (!$request->success) {
            Log::warning('Automation callback: Processing failed', [
                'post_id' => $request->post_id,
                'type' => $request->type,
                'error' => $request->error,
            ]);
            return response()->json([
                'success' => false,
                'error' => $request->error ?? 'Processing failed',
            ]);
        }

        $type = $request->type;

        if ($type === 'text_generation') {
            $updateData = [];
            if (!empty($request->caption)) {
                $updateData['main_caption'] = $request->caption;
            }
            if (!empty($request->title)) {
                $updateData['title'] = $request->title;
            }
            if (!empty($updateData)) {
                $post->update($updateData);
            }

            Log::info('Automation callback: Text generation completed', [
                'post_id' => $request->post_id,
            ]);
        } elseif ($type === 'image_generation') {
            if (!empty($request->image_base64)) {
                $this->saveBase64Image($post, $request->image_base64);
            }
            if (!empty($request->image_prompt)) {
                $post->update(['image_prompt' => $request->image_prompt]);
            }

            Log::info('Automation callback: Image generation completed', [
                'post_id' => $request->post_id,
            ]);
        } elseif ($type === 'publish') {
            // Delegate to publishing service for platform-specific handling
            $result = $this->publishingService->handleCallback([
                'post_id' => $request->post_id,
                'platform' => $request->platform ?? 'unknown',
                'success' => $request->success,
                'external_id' => $request->external_id,
                'error' => $request->error,
            ]);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            Log::info('Automation callback: Publish completed', [
                'post_id' => $request->post_id,
                'platform' => $request->platform,
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Save base64 encoded image to post media.
     */
    protected function saveBase64Image(SocialPost $post, string $base64): void
    {
        // Strip data URI prefix if present
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

        $filename = 'generated-' . Str::random(12) . '.' . $extension;
        $path = 'post-media/' . $post->id . '/' . Str::random(16) . '.' . $extension;

        Storage::disk('public')->put($path, $imageData);

        $imageSize = @getimagesizefromstring($imageData);

        $post->media()->create([
            'type' => 'image',
            'filename' => $filename,
            'path' => $path,
            'disk' => 'public',
            'mime_type' => $mimeType,
            'size' => strlen($imageData),
            'width' => $imageSize[0] ?? null,
            'height' => $imageSize[1] ?? null,
        ]);
    }
}
