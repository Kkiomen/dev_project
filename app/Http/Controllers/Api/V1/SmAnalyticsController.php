<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmAnalyticsSnapshotResource;
use App\Http\Resources\SmPerformanceScoreResource;
use App\Http\Resources\SmPostAnalyticsResource;
use App\Http\Resources\SmWeeklyReportResource;
use App\Models\Brand;
use App\Models\SmAnalyticsSnapshot;
use App\Models\SmCrisisAlert;
use App\Models\SmPerformanceScore;
use App\Models\SmPostAnalytics;
use App\Models\SmScheduledPost;
use App\Models\SmWeeklyReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmAnalyticsController extends Controller
{
    public function snapshots(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = $brand->smAnalyticsSnapshots();

        if ($request->has('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        if ($request->has('from')) {
            $query->where('snapshot_date', '>=', $request->input('from'));
        }

        if ($request->has('to')) {
            $query->where('snapshot_date', '<=', $request->input('to'));
        }

        $snapshots = $query->orderByDesc('snapshot_date')
            ->paginate(30);

        return SmAnalyticsSnapshotResource::collection($snapshots);
    }

    public function postAnalytics(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $query = SmPostAnalytics::whereHas('socialPost', fn ($q) => $q->where('brand_id', $brand->id));

        if ($request->has('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        $analytics = $query->orderByDesc('collected_at')
            ->paginate(20);

        return SmPostAnalyticsResource::collection($analytics);
    }

    public function performanceScores(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $scores = SmPerformanceScore::whereHas('socialPost', fn ($q) => $q->where('brand_id', $brand->id))
            ->orderByDesc('score')
            ->paginate(20);

        return SmPerformanceScoreResource::collection($scores);
    }

    public function weeklyReports(Request $request, Brand $brand): AnonymousResourceCollection
    {
        $this->authorize('view', $brand);

        $reports = $brand->smWeeklyReports()
            ->orderByDesc('period_start')
            ->paginate(10);

        return SmWeeklyReportResource::collection($reports);
    }

    public function weeklyReport(Request $request, Brand $brand, SmWeeklyReport $smWeeklyReport): SmWeeklyReportResource
    {
        $this->authorize('view', $brand);

        return new SmWeeklyReportResource($smWeeklyReport);
    }

    public function dashboard(Request $request, Brand $brand): JsonResponse
    {
        $this->authorize('view', $brand);

        $latestSnapshots = $brand->smAnalyticsSnapshots()
            ->orderByDesc('snapshot_date')
            ->get()
            ->unique('platform')
            ->values();

        $totalFollowers = $latestSnapshots->sum('followers');

        $avgEngagementRate = $latestSnapshots->count() > 0
            ? $latestSnapshots->avg('engagement_rate')
            : 0;

        $unreadCrisisAlerts = $brand->smCrisisAlerts()
            ->where('is_resolved', false)
            ->count();

        $pendingApprovalCount = $brand->smScheduledPosts()
            ->where('approval_status', 'pending')
            ->count();

        return response()->json([
            'latest_snapshots' => SmAnalyticsSnapshotResource::collection($latestSnapshots),
            'total_followers' => $totalFollowers,
            'avg_engagement_rate' => round($avgEngagementRate, 4),
            'unread_crisis_alerts' => $unreadCrisisAlerts,
            'pending_approval_count' => $pendingApprovalCount,
        ]);
    }
}
