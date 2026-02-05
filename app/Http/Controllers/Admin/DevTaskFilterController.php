<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\DevTaskSavedFilterResource;
use App\Models\DevTaskSavedFilter;
use Illuminate\Http\Request;

class DevTaskFilterController extends Controller
{
    public function index()
    {
        $filters = DevTaskSavedFilter::forUser(auth()->id())
            ->ordered()
            ->get();

        return DevTaskSavedFilterResource::collection($filters);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'filters' => 'required|array',
            'is_default' => 'boolean',
        ]);

        $maxPosition = DevTaskSavedFilter::forUser(auth()->id())->max('position') ?? -1;

        if (!empty($validated['is_default'])) {
            DevTaskSavedFilter::forUser(auth()->id())->update(['is_default' => false]);
        }

        $filter = DevTaskSavedFilter::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'filters' => $validated['filters'],
            'is_default' => $validated['is_default'] ?? false,
            'position' => $maxPosition + 1,
        ]);

        return new DevTaskSavedFilterResource($filter);
    }

    public function update(Request $request, DevTaskSavedFilter $filter)
    {
        $this->ensureFilterBelongsToUser($filter);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'filters' => 'sometimes|required|array',
            'is_default' => 'boolean',
        ]);

        if (!empty($validated['is_default'])) {
            DevTaskSavedFilter::forUser(auth()->id())
                ->where('id', '!=', $filter->id)
                ->update(['is_default' => false]);
        }

        $filter->update($validated);

        return new DevTaskSavedFilterResource($filter);
    }

    public function destroy(DevTaskSavedFilter $filter)
    {
        $this->ensureFilterBelongsToUser($filter);

        $filter->delete();

        return response()->json(['message' => 'Filter deleted']);
    }

    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'filter_ids' => 'required|array',
            'filter_ids.*' => 'required|string',
        ]);

        foreach ($validated['filter_ids'] as $position => $publicId) {
            DevTaskSavedFilter::forUser(auth()->id())
                ->where('public_id', $publicId)
                ->update(['position' => $position]);
        }

        return DevTaskSavedFilterResource::collection(
            DevTaskSavedFilter::forUser(auth()->id())->ordered()->get()
        );
    }

    public function setDefault(DevTaskSavedFilter $filter)
    {
        $this->ensureFilterBelongsToUser($filter);

        $filter->setAsDefault();

        return new DevTaskSavedFilterResource($filter);
    }

    protected function ensureFilterBelongsToUser(DevTaskSavedFilter $filter): void
    {
        if ($filter->user_id !== auth()->id()) {
            abort(404, 'Filter not found');
        }
    }
}
