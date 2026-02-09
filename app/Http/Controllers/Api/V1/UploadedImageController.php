<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UploadedImageResource;
use App\Models\UploadedImage;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UploadedImageController extends Controller
{
    public function __construct(
        protected ImageUploadService $imageUploadService
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $images = UploadedImage::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($request->input('per_page', 20));

        return UploadedImageResource::collection($images);
    }

    public function store(Request $request): UploadedImageResource
    {
        $request->validate([
            'image' => ['required', 'string'],
        ]);

        $image = $this->imageUploadService->uploadBase64(
            $request->input('image'),
            $request->user()
        );

        return new UploadedImageResource($image);
    }

    public function show(Request $request, UploadedImage $image): UploadedImageResource
    {
        if ($image->user_id !== $request->user()->id) {
            abort(403);
        }

        return new UploadedImageResource($image);
    }

    public function destroy(Request $request, UploadedImage $image): JsonResponse
    {
        if ($image->user_id !== $request->user()->id) {
            abort(403);
        }

        $this->imageUploadService->delete($image);

        return response()->json(['message' => 'Image deleted successfully']);
    }
}
