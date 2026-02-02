<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PostAiGenerationRequest;
use App\Models\SocialPost;
use App\Services\PostAiGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostAiController extends Controller
{
    public function __construct(
        protected PostAiGenerationService $service
    ) {}

    /**
     * Generate post content using AI.
     */
    public function generate(PostAiGenerationRequest $request): JsonResponse
    {
        try {
            $result = $this->service->generate($request->validated());

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Modify post content using AI based on user instruction.
     */
    public function modify(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => ['required', 'string'],
            'current_caption' => ['required', 'string'],
            'current_title' => ['nullable', 'string'],
            'instruction' => ['required', 'string', 'max:1000'],
        ]);

        // Verify user owns this post
        $post = SocialPost::findByPublicIdOrFail($request->post_id);
        $this->authorize('update', $post);

        try {
            $result = $this->service->modify(
                $request->current_caption,
                $request->current_title,
                $request->instruction,
                $request->user()->settings['language'] ?? 'pl'
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'modified' => false,
                'message' => __('verification.ai.error'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
