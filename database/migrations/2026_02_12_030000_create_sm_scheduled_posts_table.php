<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_id')->constrained('social_posts')->cascadeOnDelete();
            $table->string('platform');
            $table->timestamp('scheduled_at');
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('pending'); // pending, approved, scheduled, publishing, published, failed, cancelled
            $table->string('approval_status')->default('pending'); // pending, approved, rejected
            $table->text('approval_notes')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('max_retries')->default(3);
            $table->text('error_message')->nullable();
            $table->string('external_post_id')->nullable();
            $table->json('platform_response')->nullable();
            $table->timestamps();

            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'approval_status']);
            $table->index(['scheduled_at', 'status']);
            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_scheduled_posts');
    }
};
