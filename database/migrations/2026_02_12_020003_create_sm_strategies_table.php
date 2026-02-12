<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->json('content_pillars')->nullable(); // [{name, description, percentage}]
            $table->json('posting_frequency')->nullable(); // {instagram: 5, facebook: 3, ...}
            $table->json('target_audience')->nullable(); // {age_range, gender, interests[], pain_points[]}
            $table->json('goals')->nullable(); // [{goal, metric, target_value, timeframe}]
            $table->json('competitor_handles')->nullable(); // {instagram: ["@comp1"], ...}
            $table->json('content_mix')->nullable(); // {educational: 40, entertaining: 30, promotional: 20, engaging: 10}
            $table->json('optimal_times')->nullable(); // {monday: ["09:00", "18:00"], ...}
            $table->text('ai_recommendations')->nullable();
            $table->string('status')->default('draft'); // draft, active, paused, archived
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_strategies');
    }
};
