<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreFieldRequest;
use App\Http\Requests\Api\UpdateFieldRequest;
use App\Http\Resources\FieldResource;
use App\Models\Table;
use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class FieldController extends Controller
{
    public function index(Request $request, Table $table): AnonymousResourceCollection
    {
        $this->authorize('view', $table->base);

        return FieldResource::collection($table->fields);
    }

    public function store(StoreFieldRequest $request, Table $table): FieldResource
    {
        $this->authorize('update', $table->base);

        $data = $request->validated();

        // Generate IDs for choices if not provided
        if (isset($data['options']['choices'])) {
            $data['options']['choices'] = collect($data['options']['choices'])
                ->map(function ($choice) {
                    return array_merge($choice, [
                        'id' => $choice['id'] ?? Str::ulid()->toBase32(),
                        'color' => $choice['color'] ?? $this->generateColor(),
                    ]);
                })
                ->toArray();
        }

        $field = $table->fields()->create($data);

        return new FieldResource($field);
    }

    public function show(Field $field): FieldResource
    {
        $this->authorize('view', $field->table->base);

        return new FieldResource($field);
    }

    public function update(UpdateFieldRequest $request, Field $field): FieldResource
    {
        $this->authorize('update', $field->table->base);

        $data = $request->validated();

        // Handle choices update
        if (isset($data['options']['choices'])) {
            $data['options']['choices'] = collect($data['options']['choices'])
                ->map(function ($choice) {
                    return array_merge($choice, [
                        'id' => $choice['id'] ?? Str::ulid()->toBase32(),
                        'color' => $choice['color'] ?? $this->generateColor(),
                    ]);
                })
                ->toArray();

            // Merge with existing options
            $data['options'] = array_merge($field->options ?? [], $data['options']);
        }

        $field->update($data);

        return new FieldResource($field->fresh());
    }

    public function destroy(Field $field)
    {
        $this->authorize('delete', $field->table->base);

        // Don't allow deleting primary field if it's the last one
        if ($field->is_primary && $field->table->fields()->count() === 1) {
            return response()->json([
                'message' => 'Cannot delete the last primary field',
            ], 422);
        }

        $field->delete();

        return response()->noContent();
    }

    public function reorder(Request $request, Field $field)
    {
        $this->authorize('update', $field->table->base);

        $request->validate(['position' => 'required|integer|min:0']);

        $field->moveToPosition($request->position);

        return response()->json(['success' => true]);
    }

    public function addChoice(Request $request, Field $field)
    {
        $this->authorize('update', $field->table->base);

        $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $field->addChoice($request->name, $request->color);

        return new FieldResource($field->fresh());
    }

    private function generateColor(): string
    {
        $colors = ['#EF4444', '#F97316', '#EAB308', '#22C55E', '#14B8A6', '#3B82F6', '#8B5CF6', '#EC4899'];
        return $colors[array_rand($colors)];
    }
}
