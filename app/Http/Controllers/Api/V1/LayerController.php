<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLayerRequest;
use App\Http\Requests\Api\UpdateLayerRequest;
use App\Http\Requests\Api\BulkUpdateLayersRequest;
use App\Http\Resources\LayerResource;
use App\Models\Template;
use App\Models\Layer;
use Illuminate\Http\Request;
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

                // Merge properties instead of replacing
                if (isset($updateData['properties']) && $layer->properties) {
                    $updateData['properties'] = array_merge($layer->properties, $updateData['properties']);
                }

                $layer->update($updateData);
            }
        }

        return LayerResource::collection($template->layers()->ordered()->get());
    }
}
