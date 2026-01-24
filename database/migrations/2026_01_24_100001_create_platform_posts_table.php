<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_posts', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->boolean('enabled')->default(true);
            $table->text('platform_caption')->nullable();
            $table->string('video_title')->nullable();
            $table->text('video_description')->nullable();
            $table->json('hashtags')->nullable();
            $table->json('link_preview')->nullable();
            $table->string('publish_status')->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'platform']);
            $table->index(['social_post_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_posts');
    }
};
