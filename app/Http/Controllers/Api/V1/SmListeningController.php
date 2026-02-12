<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmAlertRuleResource;
use App\Http\Resources\SmListeningReportResource;
use App\Http\Resources\SmMentionResource;
use App\Models\Brand;
use App\Models\SmAlertRule;
use App\Models\SmListeningReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmListeningController extends Controller
{
    public function mentions(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = $brand->smMentions();

        if ($request->has('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        if ($request->has('sentiment')) {
            $query->where('sentiment', $request->input('sentiment'));
        }

        if ($request->has('keyword_id')) {
            $query->where('sm_monitored_keyword_id', $request->input('keyword_id'));
        }

        $mentions = $query->orderByDesc('mentioned_at')
            ->paginate(20);

        return SmMentionResource::collection($mentions);
    }

    public function alertRules(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $rules = $brand->smAlertRules()->get();

        return SmAlertRuleResource::collection($rules);
    }

    public function storeAlertRule(Request $request, Brand $brand): SmAlertRuleResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'alert_type' => ['required', 'string', 'max:255'],
            'threshold' => ['required', 'integer', 'min:1'],
            'timeframe' => ['required', 'string', 'max:100'],
            'notify_via' => ['required', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rule = $brand->smAlertRules()->create($validated);

        return new SmAlertRuleResource($rule);
    }

    public function updateAlertRule(Request $request, Brand $brand, SmAlertRule $smAlertRule): SmAlertRuleResource
    {
        $this->authorize('update', $brand);

        $validated = $request->validate([
            'alert_type' => ['sometimes', 'string', 'max:255'],
            'threshold' => ['sometimes', 'integer', 'min:1'],
            'timeframe' => ['sometimes', 'string', 'max:100'],
            'notify_via' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $smAlertRule->update($validated);

        return new SmAlertRuleResource($smAlertRule);
    }

    public function destroyAlertRule(Request $request, Brand $brand, SmAlertRule $smAlertRule): JsonResponse
    {
        $this->authorize('update', $brand);

        $smAlertRule->delete();

        return response()->json(null, 204);
    }

    public function reports(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $reports = $brand->smListeningReports()
            ->orderByDesc('period_start')
            ->paginate(10);

        return SmListeningReportResource::collection($reports);
    }

    public function report(Request $request, Brand $brand, SmListeningReport $smListeningReport): SmListeningReportResource
    {
        $this->authorize('view', $brand);

        return new SmListeningReportResource($smListeningReport);
    }
}
