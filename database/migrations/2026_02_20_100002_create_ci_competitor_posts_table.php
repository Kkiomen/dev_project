<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_competitor_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ci_competitor_id')->constrained('ci_competitors')->cascadeOnDelete();
            $table->foreignId('ci_competitor_account_id')->constrained('ci_competitor_accounts')->cascadeOnDelete();
            $table->string('platform', 50);
            $table->string('external_post_id');
            $table->string('post_type', 50)->nullable();
            $table->text('caption')->nullable();
            $table->json('hashtags')->nullable();
            $table->string('post_url')->nullable();
            $table->timestamp('posted_at')->nullable();

            // Engagement metrics
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('saves')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->decimal('engagement_rate', 8, 4)->default(0);

            // AI analysis
            $table->json('ai_analysis')->nullable();
            $table->timestamp('analyzed_at')->nullable();

            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['ci_competitor_account_id', 'external_post_id'], 'ci_posts_account_external_unique');
            $table->index(['brand_id', 'platform', 'posted_at']);
            $table->index(['ci_competitor_id', 'engagement_rate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_competitor_posts');
    }
};
