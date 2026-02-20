<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_scrape_runs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('actor_type', 50);
            $table->string('apify_run_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('input_params')->nullable();
            $table->unsignedInteger('results_count')->default(0);
            $table->decimal('estimated_cost', 8, 4)->default(0);
            $table->decimal('actual_cost', 8, 4)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'actor_type', 'created_at']);
            $table->index('apify_run_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_scrape_runs');
    }
};
