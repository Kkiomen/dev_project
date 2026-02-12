<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->date('snapshot_date');
            $table->unsignedInteger('followers')->default(0);
            $table->unsignedInteger('following')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedInteger('profile_views')->default(0);
            $table->unsignedInteger('website_clicks')->default(0);
            $table->decimal('engagement_rate', 8, 4)->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->json('extra_metrics')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'platform', 'snapshot_date']);
            $table->index(['brand_id', 'snapshot_date']);
        });

        Schema::create('sm_post_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->string('platform');
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('saves')->default(0);
            $table->unsignedBigInteger('reach')->default(0);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('video_views')->default(0);
            $table->decimal('engagement_rate', 8, 4)->default(0);
            $table->json('extra_metrics')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'platform']);
            $table->index(['platform', 'engagement_rate']);
        });

        Schema::create('sm_performance_scores', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('social_post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 0-100
            $table->json('analysis')->nullable(); // {strengths: [], weaknesses: [], factors: {}}
            $table->text('recommendations')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamps();

            $table->index(['social_post_id']);
            $table->index(['score']);
        });

        Schema::create('sm_weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('summary')->nullable(); // {highlights: [], key_metrics: {}}
            $table->json('top_posts')->nullable(); // [{post_id, score, platform}]
            $table->text('recommendations')->nullable();
            $table->json('growth_metrics')->nullable(); // {followers_change: {}, engagement_change: {}}
            $table->json('platform_breakdown')->nullable(); // per-platform stats
            $table->string('status')->default('generating'); // generating, ready, archived
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'period_start']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_weekly_reports');
        Schema::dropIfExists('sm_performance_scores');
        Schema::dropIfExists('sm_post_analytics');
        Schema::dropIfExists('sm_analytics_snapshots');
    }
};
