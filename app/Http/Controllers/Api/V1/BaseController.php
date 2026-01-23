<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBaseRequest;
use App\Http\Requests\Api\UpdateBaseRequest;
use App\Http\Resources\BaseResource;
use App\Models\Base;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $bases = Base::forUser($request->user())
            ->withCount('tables')
            ->latest()
            ->paginate($request->get('per_page', 20));

        return BaseResource::collection($bases);
    }

    public function store(StoreBaseRequest $request): BaseResource
    {
        $base = $request->user()->bases()->create($request->validated());

        // Create default table with primary field
        $base->createTable('Table 1');

        return new BaseResource($base->load('tables.fields'));
    }

    public function show(Request $request, Base $base): BaseResource
    {
        $this->authorize('view', $base);

        return new BaseResource($base->load('tables'));
    }

    public function update(UpdateBaseRequest $request, Base $base): BaseResource
    {
        $this->authorize('update', $base);

        $base->update($request->validated());

        return new BaseResource($base);
    }

    public function destroy(Base $base)
    {
        $this->authorize('delete', $base);

        $base->delete();

        return response()->noContent();
    }
}
