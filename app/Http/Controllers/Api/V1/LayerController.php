<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLayerRequest;
use App\Http\Requests\Api\UpdateLayerRequest;
use App\Http\Requests\Api\BulkUpdateLayersRequest;
use App\Http\Resources\LayerResource;
use App\Http\Resources\TemplateResource;
use App\Models\Template;
use App\Models\Layer;
use App\Enums\LayerType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LayerController extends Controller
{
    public function index(Request $request, Template $template): AnonymousResourceCollection
    {
        $this->authorize('view', $template);

        return LayerResource::collection($template->layers);
    }

    public function store(StoreLayerRequest $request, Template $template): LayerResource
    {
        $this->authorize('update', $template);

        $data = $request->validated();

        // Set default properties based on layer type if not provided
        if (!isset($data['properties'])) {
            $type = \App\Enums\LayerType::from($data['type']);
            $data['properties'] = $type->defaultProperties();
        }

        $layer = $template->layers()->create($data);

        return new LayerResource($layer);
    }

    public function show(Layer $layer): LayerResource
    {
        $this->authorize('view', $layer->template);

        return new LayerResource($layer);
    }

    public function update(UpdateLayerRequest $request, Layer $layer): LayerResource
    {
        $this->authorize('update', $layer->template);

        $data = $request->validated();

        // Merge properties instead of replacing
        if (isset($data['properties']) && $layer->properties) {
            $data['properties'] = array_merge($layer->properties, $data['properties']);
        }

        $layer->update($data);

        return new LayerResource($layer->fresh());
    }

    public function destroy(Layer $layer)
    {
        $this->authorize('delete', $layer->template);

        $layer->delete();

        return response()->noContent();
    }

    public function reorder(Request $request, Layer $layer)
    {
        $this->authorize('update', $layer->template);

        $request->validate(['position' => 'required|integer|min:0']);

        $layer->moveToPosition($request->position);

        return response()->json(['success' => true]);
    }

    public function bulkUpdate(BulkUpdateLayersRequest $request, Template $template): AnonymousResourceCollection
    {
        $this->authorize('update', $template);

        $layersData = $request->validated('layers');

        foreach ($layersData as $layerData) {
            $layer = Layer::where('public_id', $layerData['id'])
                ->where('template_id', $template->id)
                ->first();

            if ($layer) {
                $updateData = collect($layerData)->except('id')->toArray();

                // Filter out null values for fields that have NOT NULL constraint
                $notNullFields = ['visible', 'locked', 'rotation', 'scale_x', 'scale_y', 'x', 'y', 'width', 'height'];
                foreach ($notNullFields as $field) {
                    if (array_key_exists($field, $updateData) && $updateData[$field] === null) {
                        unset($updateData[$field]);
                    }
                }

                // Merge properties instead of replacing
                if (isset($updateData['properties']) && $layer->properties) {
                    $updateData['properties'] = array_merge($layer->properties, $updateData['properties']);
                }

                $layer->update($updateData);
            }
        }

        return LayerResource::collection($template->layers()->ordered()->get());
    }

    /**
     * Create a new template from a group layer.
     * This extracts a group and its children into a standalone template.
     */
    public function createTemplateFromGroup(Request $request, Layer $layer): JsonResponse
    {
        $this->authorize('update', $layer->template);

        // Validate that the layer is a group
        if ($layer->type !== LayerType::GROUP) {
            return response()->json([
                'message' => __('graphics.layers.notAGroup'),
            ], 422);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
            'add_to_library' => 'boolean',
        ]);

        // Get all descendants of this group (recursively)
        $descendants = $this->getAllDescendants($layer);

        if ($descendants->isEmpty()) {
            return response()->json([
                'message' => __('graphics.layers.groupEmpty'),
            ], 422);
        }

        // Calculate bounding box of all descendants
        $bounds = $this->calculateBounds($descendants, $layer);

        // Create new template
        $template = Template::create([
            'user_id' => $request->user()->id,
            'name' => $request->input('name', $layer->name ?? __('graphics.templates.newFromGroup')),
            'width' => (int) ceil($bounds['width']),
            'height' => (int) ceil($bounds['height']),
            'background_color' => $layer->template->background_color ?? '#ffffff',
            'is_library' => $request->boolean('add_to_library', false),
        ]);

        // Copy layers to new template with position adjustment
        $this->copyLayersToTemplate($descendants, $template, $bounds['minX'], $bounds['minY'], $layer->id);

        // Copy fonts from original template
        foreach ($layer->template->fonts as $font) {
            $newFont = $font->replicate();
            $newFont->template_id = $template->id;
            $newFont->save();
        }

        return response()->json([
            'message' => __('graphics.library.groupTemplateCreated'),
            'data' => new TemplateResource($template->load('layers')),
        ], 201);
    }

    /**
     * Get all descendants of a layer recursively.
     */
    private function getAllDescendants(Layer $layer): \Illuminate\Support\Collection
    {
        $descendants = collect();

        foreach ($layer->children as $child) {
            $descendants->push($child);
            if ($child->type === LayerType::GROUP) {
                $descendants = $descendants->merge($this->getAllDescendants($child));
            }
        }

        return $descendants;
    }

    /**
     * Calculate the bounding box of all layers.
     */
    private function calculateBounds(\Illuminate\Support\Collection $layers, Layer $groupLayer): array
    {
        // Start with the group's own position as the base
        $minX = $groupLayer->x ?? 0;
        $minY = $groupLayer->y ?? 0;
        $maxX = $minX + ($groupLayer->width ?? 0);
        $maxY = $minY + ($groupLayer->height ?? 0);

        foreach ($layers as $layer) {
            $layerMinX = $layer->x ?? 0;
            $layerMinY = $layer->y ?? 0;
            $layerMaxX = $layerMinX + ($layer->width ?? 0);
            $layerMaxY = $layerMinY + ($layer->height ?? 0);

            $minX = min($minX, $layerMinX);
            $minY = min($minY, $layerMinY);
            $maxX = max($maxX, $layerMaxX);
            $maxY = max($maxY, $layerMaxY);
        }

        return [
            'minX' => $minX,
            'minY' => $minY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
        ];
    }

    /**
     * Copy layers to a new template with position adjustment.
     */
    private function copyLayersToTemplate(
        \Illuminate\Support\Collection $layers,
        Template $template,
        float $offsetX,
        float $offsetY,
        int $excludeParentId
    ): void {
        // Build mapping from old layer ID to new layer ID
        $idMapping = [];

        // First pass: create all layers without parent_id
        foreach ($layers as $layer) {
            $newLayer = $layer->replicate(['public_id', 'parent_id']);
            $newLayer->template_id = $template->id;
            $newLayer->parent_id = null;

            // Adjust position relative to the group's position
            $newLayer->x = ($layer->x ?? 0) - $offsetX;
            $newLayer->y = ($layer->y ?? 0) - $offsetY;

            $newLayer->save();

            $idMapping[$layer->id] = $newLayer->id;
        }

        // Second pass: update parent_id using the mapping (excluding the original group)
        foreach ($layers as $layer) {
            if ($layer->parent_id && $layer->parent_id !== $excludeParentId) {
                if (isset($idMapping[$layer->parent_id])) {
                    $newLayerId = $idMapping[$layer->id];
                    Layer::where('id', $newLayerId)->update([
                        'parent_id' => $idMapping[$layer->parent_id],
                    ]);
                }
            }
        }
    }
}
