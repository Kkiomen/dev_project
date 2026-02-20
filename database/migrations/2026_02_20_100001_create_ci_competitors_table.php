<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_competitors', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'is_active']);
        });

        Schema::create('ci_competitor_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('ci_competitor_id')->constrained('ci_competitors')->cascadeOnDelete();
            $table->string('platform', 50);
            $table->string('handle');
            $table->string('external_id')->nullable();
            $table->json('profile_data')->nullable();
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['ci_competitor_id', 'platform', 'handle']);
            $table->index('platform');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_competitor_accounts');
        Schema::dropIfExists('ci_competitors');
    }
};
