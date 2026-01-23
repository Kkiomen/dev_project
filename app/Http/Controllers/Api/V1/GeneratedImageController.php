<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GeneratedImageResource;
use App\Models\Template;
use App\Models\GeneratedImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class GeneratedImageController extends Controller
{
    public function index(Request $request, Template $template): AnonymousResourceCollection
    {
        $this->authorize('view', $template);

        $perPage = $request->input('per_page', 20);

        $images = $template->generatedImages()
            ->orderByDesc('generated_at')
            ->paginate($perPage);

        return GeneratedImageResource::collection($images);
    }

    public function store(Request $request, Template $template): GeneratedImageResource
    {
        $this->authorize('update', $template);

        $request->validate([
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:20480'],
            'modifications' => ['nullable', 'json'],
        ]);

        $file = $request->file('image');
        $path = $file->store('templates/' . $template->public_id . '/generated', 'public');

        $modifications = $request->input('modifications')
            ? json_decode($request->input('modifications'), true)
            : null;

        $image = $template->generatedImages()->create([
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'modifications' => $modifications,
            'generated_at' => now(),
        ]);

        return new GeneratedImageResource($image);
    }

    public function show(GeneratedImage $generatedImage): GeneratedImageResource
    {
        $this->authorize('view', $generatedImage->template);

        return new GeneratedImageResource($generatedImage);
    }

    public function destroy(GeneratedImage $generatedImage)
    {
        $this->authorize('delete', $generatedImage->template);

        $generatedImage->delete();

        return response()->noContent();
    }

    public function bulkDestroy(Request $request, Template $template)
    {
        $this->authorize('delete', $template);

        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string'],
        ]);

        $images = GeneratedImage::whereIn('public_id', $request->ids)
            ->where('template_id', $template->id)
            ->get();

        foreach ($images as $image) {
            $image->delete();
        }

        return response()->json([
            'deleted' => $images->count(),
        ]);
    }
}
