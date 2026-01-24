<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StockPhoto\StockPhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockPhotoController extends Controller
{
    public function __construct(
        protected StockPhotoService $stockPhotoService
    ) {}

    /**
     * Search for stock photos.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'keywords' => ['required', 'array'],
            'keywords.*' => ['string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        if (!$this->stockPhotoService->isAvailable()) {
            return response()->json([
                'message' => 'Stock photo service is not configured',
                'photos' => [],
            ], 503);
        }

        $photos = $this->stockPhotoService->search(
            $request->keywords,
            $request->integer('per_page', 9)
        );

        return response()->json([
            'photos' => $photos,
            'providers' => $this->stockPhotoService->getAvailableProviders(),
        ]);
    }

    /**
     * Get featured/curated photos.
     */
    public function featured(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        if (!$this->stockPhotoService->isAvailable()) {
            return response()->json([
                'message' => 'Stock photo service is not configured',
                'photos' => [],
            ], 503);
        }

        $photos = $this->stockPhotoService->featured(
            $request->integer('per_page', 9)
        );

        return response()->json([
            'photos' => $photos,
            'providers' => $this->stockPhotoService->getAvailableProviders(),
        ]);
    }
}
