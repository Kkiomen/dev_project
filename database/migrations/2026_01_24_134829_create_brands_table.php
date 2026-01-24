<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->text('description')->nullable();

            // Target audience settings
            $table->json('target_audience')->nullable();
            // Structure: {
            //     "age_range": "25-40",
            //     "gender": "all",
            //     "interests": ["marketing", "business"],
            //     "pain_points": ["no time", "don't know what to post"]
            // }

            // Voice and personality settings
            $table->json('voice')->nullable();
            // Structure: {
            //     "tone": "professional",
            //     "personality": ["expert", "friendly", "motivational"],
            //     "language": "pl",
            //     "emoji_usage": "sometimes"
            // }

            // Content pillars
            $table->json('content_pillars')->nullable();
            // Structure: [
            //     {"name": "Marketing tips", "description": "Practical tips...", "percentage": 40},
            //     {"name": "Case studies", "description": "Client success...", "percentage": 30},
            // ]

            // Posting preferences
            $table->json('posting_preferences')->nullable();
            // Structure: {
            //     "frequency": {"facebook": 3, "instagram": 5, "youtube": 1},
            //     "best_times": {"facebook": ["09:00", "18:00"], "instagram": ["12:00", "20:00"]},
            //     "auto_schedule": true
            // }

            // Connected platforms
            $table->json('platforms')->nullable();
            // Structure: {
            //     "facebook": {"enabled": true, "page_id": "..."},
            //     "instagram": {"enabled": true, "account_id": "..."},
            //     "youtube": {"enabled": false, "channel_id": null}
            // }

            $table->boolean('onboarding_completed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_active']);
        });

        // Add brand_id to social_posts
        Schema::table('social_posts', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('brand_id');
        });

        // Add brand_id to templates
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });

        Schema::table('social_posts', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};
