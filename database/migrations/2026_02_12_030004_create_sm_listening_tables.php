<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_monitored_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('keyword');
            $table->string('platform')->nullable(); // null = all platforms
            $table->string('category')->nullable(); // brand, product, competitor, industry
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('mention_count')->default(0);
            $table->timestamp('last_mention_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'is_active']);
            $table->index('keyword');
        });

        Schema::create('sm_mentions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sm_monitored_keyword_id')->nullable();
            $table->string('platform');
            $table->string('source_url', 2048)->nullable();
            $table->string('author_handle')->nullable();
            $table->string('author_name')->nullable();
            $table->text('text');
            $table->string('sentiment')->nullable(); // positive, neutral, negative
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('engagement')->default(0);
            $table->timestamp('mentioned_at')->nullable();
            $table->timestamps();

            $table->foreign('sm_monitored_keyword_id')
                ->references('id')
                ->on('sm_monitored_keywords')
                ->nullOnDelete();

            $table->index(['brand_id', 'platform']);
            $table->index(['brand_id', 'sentiment']);
            $table->index('sm_monitored_keyword_id');
            $table->index('mentioned_at');
        });

        Schema::create('sm_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('alert_type'); // mention_spike, negative_sentiment, competitor_mention, keyword_trending
            $table->unsignedInteger('threshold')->default(5);
            $table->string('timeframe')->default('1h'); // 1h, 6h, 24h
            $table->json('notify_via')->nullable(); // ["email", "push", "slack"]
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'is_active']);
        });

        Schema::create('sm_listening_reports', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('share_of_voice')->nullable(); // {brand: 45, competitor1: 30, ...}
            $table->json('sentiment_breakdown')->nullable(); // {positive: 60, neutral: 25, negative: 15}
            $table->json('top_mentions')->nullable(); // [{text, author, platform, reach}]
            $table->json('trending_keywords')->nullable();
            $table->text('ai_summary')->nullable();
            $table->string('status')->default('generating');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_listening_reports');
        Schema::dropIfExists('sm_alert_rules');
        Schema::dropIfExists('sm_mentions');
        Schema::dropIfExists('sm_monitored_keywords');
    }
};
