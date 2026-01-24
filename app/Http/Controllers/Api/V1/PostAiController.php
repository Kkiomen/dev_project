<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PostAiGenerationRequest;
use App\Services\PostAiGenerationService;
use Illuminate\Http\JsonResponse;

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
}
