<?php

use App\Models\Brand;
use App\Models\SmAnalyticsSnapshot;
use App\Models\SmCrisisAlert;
use App\Models\SmScheduledPost;
use App\Models\SmWeeklyReport;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->brand = Brand::create([
        'user_id' => $this->user->id,
        'name' => 'Test Brand',
        'is_active' => true,
    ]);
});

describe('SmAnalytics API', function () {

    it('requires authentication', function () {
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-analytics/dashboard")
            ->assertUnauthorized();
    });

    it('dashboard returns aggregated stats', function () {
        // Create snapshots for different platforms
        SmAnalyticsSnapshot::create([
            'brand_id' => $this->brand->id,
            'platform' => 'instagram',
            'snapshot_date' => today(),
            'followers' => 5000,
            'following' => 200,
            'reach' => 10000,
            'impressions' => 20000,
            'profile_views' => 100,
            'website_clicks' => 50,
            'engagement_rate' => 3.5000,
            'posts_count' => 10,
        ]);

        SmAnalyticsSnapshot::create([
            'brand_id' => $this->brand->id,
            'platform' => 'facebook',
            'snapshot_date' => today(),
            'followers' => 3000,
            'following' => 100,
            'reach' => 8000,
            'impressions' => 15000,
            'profile_views' => 80,
            'website_clicks' => 30,
            'engagement_rate' => 2.5000,
            'posts_count' => 8,
        ]);

        // Create unresolved crisis alerts
        SmCrisisAlert::factory()->count(2)->create([
            'brand_id' => $this->brand->id,
            'is_resolved' => false,
        ]);

        // Create pending approval posts
        SmScheduledPost::factory()->count(3)->create([
            'brand_id' => $this->brand->id,
            'approval_status' => 'pending',
        ]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-analytics/dashboard")
            ->assertOk()
            ->assertJsonStructure([
                'latest_snapshots',
                'total_followers',
                'avg_engagement_rate',
                'unread_crisis_alerts',
                'pending_approval_count',
            ])
            ->assertJsonPath('total_followers', 8000)
            ->assertJsonPath('unread_crisis_alerts', 2)
            ->assertJsonPath('pending_approval_count', 3);
    });

    it('can list snapshots', function () {
        SmAnalyticsSnapshot::create([
            'brand_id' => $this->brand->id,
            'platform' => 'instagram',
            'snapshot_date' => today(),
            'followers' => 5000,
            'following' => 200,
            'reach' => 10000,
            'impressions' => 20000,
            'profile_views' => 100,
            'website_clicks' => 50,
            'engagement_rate' => 3.5000,
            'posts_count' => 10,
        ]);

        SmAnalyticsSnapshot::create([
            'brand_id' => $this->brand->id,
            'platform' => 'facebook',
            'snapshot_date' => today(),
            'followers' => 3000,
            'following' => 100,
            'reach' => 8000,
            'impressions' => 15000,
            'profile_views' => 80,
            'website_clicks' => 30,
            'engagement_rate' => 2.5000,
            'posts_count' => 8,
        ]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-analytics/snapshots")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter snapshots by platform', function () {
        SmAnalyticsSnapshot::create([
            'brand_id' => $this->brand->id,
            'platform' => 'instagram',
            'snapshot_date' => today(),
            'followers' => 5000,
            'following' => 200,
            'reach' => 10000,
            'impressions' => 20000,
            'profile_views' => 100,
            'website_clicks' => 50,
            'engagement_rate' => 3.5000,
            'posts_count' => 10,
        ]);

        SmAnalyticsSnapshot::create([
            'brand_id' => $this->brand->id,
            'platform' => 'facebook',
            'snapshot_date' => today(),
            'followers' => 3000,
            'following' => 100,
            'reach' => 8000,
            'impressions' => 15000,
            'profile_views' => 80,
            'website_clicks' => 30,
            'engagement_rate' => 2.5000,
            'posts_count' => 8,
        ]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-analytics/snapshots?platform=instagram")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can list weekly reports', function () {
        SmWeeklyReport::factory()->count(2)->create(['brand_id' => $this->brand->id]);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/brands/{$this->brand->public_id}/sm-analytics/weekly-reports")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    });

});
