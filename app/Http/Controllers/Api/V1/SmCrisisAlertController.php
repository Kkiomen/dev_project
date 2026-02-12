<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmCrisisAlertResource;
use App\Models\Brand;
use App\Models\SmCrisisAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmCrisisAlertController extends Controller
{
    public function index(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = $brand->smCrisisAlerts();

        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->has('is_resolved')) {
            $query->where('is_resolved', filter_var($request->input('is_resolved'), FILTER_VALIDATE_BOOLEAN));
        }

        $alerts = $query->orderByDesc('created_at')
            ->paginate(20);

        return SmCrisisAlertResource::collection($alerts);
    }

    public function show(Request $request, Brand $brand, SmCrisisAlert $smCrisisAlert): SmCrisisAlertResource
    {
        $this->authorize('view', $brand);

        return new SmCrisisAlertResource($smCrisisAlert);
    }

    public function resolve(Request $request, Brand $brand, SmCrisisAlert $smCrisisAlert): SmCrisisAlertResource
    {
        $this->authorize('update', $brand);

        $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $smCrisisAlert->resolve($request->resolution_notes);

        return new SmCrisisAlertResource($smCrisisAlert);
    }
}
