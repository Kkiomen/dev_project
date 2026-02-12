<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_content_plans', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('sm_strategy_id')->nullable();
            $table->unsignedSmallInteger('month'); // 1-12
            $table->unsignedSmallInteger('year');
            $table->string('status')->default('draft'); // draft, generating, active, completed, archived
            $table->json('summary')->nullable(); // AI-generated plan summary
            $table->unsignedInteger('total_slots')->default(0);
            $table->unsignedInteger('completed_slots')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('sm_strategy_id')
                ->references('id')
                ->on('sm_strategies')
                ->nullOnDelete();

            $table->unique(['brand_id', 'month', 'year']);
            $table->index('status');
        });

        Schema::create('sm_content_plan_slots', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->unsignedBigInteger('sm_content_plan_id');
            $table->date('scheduled_date');
            $table->string('scheduled_time', 5)->nullable(); // HH:MM
            $table->string('platform');
            $table->string('content_type')->default('post'); // post, carousel, reel, story, thread, poll
            $table->string('topic')->nullable();
            $table->text('description')->nullable();
            $table->string('pillar')->nullable(); // content pillar reference
            $table->string('status')->default('planned'); // planned, content_ready, media_ready, approved, published, skipped
            $table->foreignId('social_post_id')->nullable()->constrained('social_posts')->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('sm_content_plan_id')
                ->references('id')
                ->on('sm_content_plans')
                ->cascadeOnDelete();

            $table->index(['sm_content_plan_id', 'scheduled_date']);
            $table->index(['status']);
            $table->index(['platform', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_content_plan_slots');
        Schema::dropIfExists('sm_content_plans');
    }
};
