<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TemplateLibraryController extends Controller
{
    /**
     * List all library templates (available to all authenticated users).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Template::library()
            ->withCount('layers')
            ->orderBy('library_category')
            ->orderBy('name');

        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('library_category', $request->category);
        }

        $templates = $query->get();

        return TemplateResource::collection($templates);
    }

    /**
     * Get library categories.
     */
    public function categories(): array
    {
        $categories = Template::library()
            ->whereNotNull('library_category')
            ->distinct()
            ->pluck('library_category')
            ->sort()
            ->values()
            ->toArray();

        return [
            'categories' => $categories,
        ];
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
            'category' => 'nullable|string|max:100',
        ]);

        $template->addToLibrary($validated['category'] ?? null);

        return new TemplateResource($template->fresh());
    }

    /**
     * Remove template from library (admin only).
     */
    public function removeFromLibrary(Template $template): TemplateResource
    {
        $this->authorize('update', $template);

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

        // Force delete - admin removes from library completely
        $template->forceDelete();

        return response()->noContent();
    }
}
