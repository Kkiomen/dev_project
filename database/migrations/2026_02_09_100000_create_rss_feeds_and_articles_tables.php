<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_feeds', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('url', 2048);
            $table->string('url_hash', 64); // SHA-256 for unique index
            $table->string('site_url', 2048)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('last_error')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->unsignedInteger('articles_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'status']);
            $table->unique(['brand_id', 'url_hash']);
        });

        Schema::create('rss_articles', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('rss_feed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('guid', 2048);
            $table->string('guid_hash', 64); // SHA-256 for unique index
            $table->string('title', 1024);
            $table->text('description')->nullable();
            $table->string('url', 2048);
            $table->string('author', 255)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->json('categories')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'published_at']);
            $table->index(['rss_feed_id', 'published_at']);
            $table->index('published_at');
            $table->unique(['rss_feed_id', 'guid_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_articles');
        Schema::dropIfExists('rss_feeds');
    }
};
