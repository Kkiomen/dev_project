<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TemplateLibraryController extends Controller
{
    /**
     * List all library templates (available to all authenticated users).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $templates = Template::library()
            ->withCount('layers')
            ->orderBy('created_at', 'desc')
            ->get();

        return TemplateResource::collection($templates);
    }

    /**
     * Copy a library template to user's collection.
     */
    public function copy(Request $request, Template $template): TemplateResource
    {
        // Verify it's a library template
        if (!$template->is_library) {
            abort(404, 'Template not found in library.');
        }

        $newTemplate = $template->copyToUser($request->user()->id);

        return new TemplateResource($newTemplate);
    }

    /**
     * Add current template to library (admin only).
     */
    public function addToLibrary(Request $request, Template $template): TemplateResource
    {
        $this->authorize('update', $template);

        $validated = $request->validate([
            'thumbnail' => 'nullable|string',
        ]);

        // Save thumbnail if provided
        $thumbnailPath = null;
        if (!empty($validated['thumbnail'])) {
            $thumbnailPath = $this->saveThumbnail($validated['thumbnail'], $template);
        }

        $template->addToLibrary(null, $thumbnailPath);

        return new TemplateResource($template->fresh());
    }

    /**
     * Remove template from library (admin only).
     * Admin can remove any template from library, owner can remove their own.
     */
    public function removeFromLibrary(Request $request, Template $template): TemplateResource
    {
        $user = $request->user();

        // Must be admin or owner
        if (!$user->isAdmin() && $user->id !== $template->user_id) {
            abort(403, 'Unauthorized.');
        }

        if (!$template->is_library) {
            abort(400, 'Template is not in library.');
        }

        $template->removeFromLibrary();

        return new TemplateResource($template->fresh());
    }

    /**
     * Delete library template permanently (admin only).
     */
    public function destroy(Template $template)
    {
        if (!$template->is_library) {
            abort(404, 'Template not found in library.');
        }

        // Delete thumbnail if exists
        if ($template->thumbnail_path) {
            Storage::disk('public')->delete($template->thumbnail_path);
        }

        // Force delete - admin removes from library completely
        $template->forceDelete();

        return response()->noContent();
    }

    /**
     * Save thumbnail from base64 data URL.
     */
    protected function saveThumbnail(string $base64Data, Template $template): ?string
    {
        // Extract the base64 data from the data URL
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $imageData = base64_decode($base64Data);

            if ($imageData === false) {
                return null;
            }

            // Generate unique filename
            $filename = 'thumbnails/' . Str::uuid() . '.' . $extension;

            // Save to storage
            Storage::disk('public')->put($filename, $imageData);

            return $filename;
        }

        return null;
    }
}
