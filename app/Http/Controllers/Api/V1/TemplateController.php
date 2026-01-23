<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GenerateTemplateRequest;
use App\Http\Requests\Api\StoreTemplateRequest;
use App\Http\Requests\Api\UpdateTemplateRequest;
use App\Http\Resources\LayerResource;
use App\Http\Resources\TemplateResource;
use App\Models\Base;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class TemplateController extends Controller
{
    /**
     * List all templates for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $templates = Template::where('user_id', $request->user()->id)
            ->withCount('layers')
            ->ordered()
            ->get();

        return TemplateResource::collection($templates);
    }

    /**
     * List templates for a specific base (for automation).
     */
    public function indexByBase(Request $request, Base $base): AnonymousResourceCollection
    {
        $this->authorize('view', $base);

        $templates = $base->templates()
            ->withCount('layers')
            ->ordered()
            ->get();

        return TemplateResource::collection($templates);
    }

    /**
     * Create a new template for the authenticated user.
     */
    public function store(StoreTemplateRequest $request): TemplateResource
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        // If base_id is provided, verify ownership
        if (isset($data['base_id'])) {
            $base = Base::findOrFail($data['base_id']);
            $this->authorize('view', $base);
        }

        $template = Template::create($data);

        return new TemplateResource($template);
    }

    public function show(Request $request, Template $template): TemplateResource
    {
        $this->authorize('view', $template);

        $template->load(['layers', 'fonts']);

        return new TemplateResource($template);
    }

    public function update(UpdateTemplateRequest $request, Template $template): TemplateResource
    {
        $this->authorize('update', $template);

        $template->update($request->validated());

        return new TemplateResource($template);
    }

    public function destroy(Template $template)
    {
        $this->authorize('delete', $template);

        // Delete thumbnail if exists
        if ($template->thumbnail_path) {
            Storage::delete($template->thumbnail_path);
        }

        // Delete background image if exists
        if ($template->background_image) {
            Storage::delete($template->background_image);
        }

        $template->delete();

        return response()->noContent();
    }

    public function reorder(Request $request, Template $template)
    {
        $this->authorize('update', $template);

        $request->validate(['position' => 'required|integer|min:0']);

        $template->moveToPosition($request->position);

        return response()->json(['success' => true]);
    }

    public function duplicate(Request $request, Template $template): TemplateResource
    {
        $this->authorize('duplicate', $template);

        $newTemplate = $template->duplicate();

        return new TemplateResource($newTemplate);
    }

    public function uploadThumbnail(Request $request, Template $template): TemplateResource
    {
        $this->authorize('update', $template);

        $request->validate([
            'thumbnail' => ['required', 'image', 'max:2048'],
        ]);

        // Delete old thumbnail if exists
        if ($template->thumbnail_path) {
            Storage::delete($template->thumbnail_path);
        }

        $path = $request->file('thumbnail')->store('templates/thumbnails', 'public');

        $template->update(['thumbnail_path' => $path]);

        return new TemplateResource($template);
    }

    public function uploadBackgroundImage(Request $request, Template $template): TemplateResource
    {
        $this->authorize('update', $template);

        $request->validate([
            'image' => ['required', 'image', 'max:10240'],
        ]);

        // Delete old background if exists
        if ($template->background_image) {
            Storage::delete($template->background_image);
        }

        $path = $request->file('image')->store('templates/backgrounds', 'public');

        $template->update(['background_image' => $path]);

        return new TemplateResource($template);
    }

    /**
     * Generate template preview with modifications applied.
     *
     * Returns the template data with layers modified according to the provided modifications.
     * The actual image rendering happens client-side using Konva.js.
     * The original template remains unchanged.
     */
    public function generate(GenerateTemplateRequest $request, Template $template)
    {
        $this->authorize('view', $template);

        $template->load(['layers', 'fonts']);

        $modifications = $request->input('modifications', []);
        $format = $request->input('format', 'png');
        $quality = $request->input('quality', 100);
        $scale = $request->input('scale', 1);

        // Create modified layers data without changing the database
        $modifiedLayers = $template->layers->map(function ($layer) use ($modifications) {
            $layerData = $layer->toArray();

            // Check if there are modifications for this layer (by name)
            if (isset($modifications[$layer->name])) {
                $layerModifications = $modifications[$layer->name];

                // Merge modifications into properties
                $properties = $layer->properties ?? [];
                foreach ($layerModifications as $key => $value) {
                    // Handle top-level layer properties vs nested properties
                    if (in_array($key, ['text', 'src', 'fill', 'fillImage', 'fillFit', 'stroke', 'fontFamily', 'fontSize'])) {
                        $properties[$key] = $value;
                    } else {
                        $layerData[$key] = $value;
                    }
                }

                $layerData['properties'] = $properties;
            }

            return $layerData;
        });

        return response()->json([
            'success' => true,
            'template' => [
                'id' => $template->public_id,
                'name' => $template->name,
                'width' => $template->width,
                'height' => $template->height,
                'background_color' => $template->background_color,
                'background_image' => $template->background_image,
            ],
            'layers' => $modifiedLayers,
            'fonts' => $template->fonts->map(fn ($font) => [
                'font_family' => $font->font_family,
                'font_url' => $font->font_file ? asset('storage/' . $font->font_file) : null,
                'font_weight' => $font->font_weight,
                'font_style' => $font->font_style,
            ]),
            'render_options' => [
                'format' => $format,
                'quality' => $quality,
                'scale' => $scale,
            ],
        ]);
    }
}
