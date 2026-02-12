<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_design_templates', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('type'); // post, story, carousel_slide, cover, reel_cover
            $table->string('platform')->nullable(); // null = all platforms
            $table->json('canvas_json')->nullable(); // layout/design data
            $table->unsignedInteger('width')->default(1080);
            $table->unsignedInteger('height')->default(1080);
            $table->string('thumbnail_path')->nullable();
            $table->string('category')->nullable(); // quote, promo, education, announcement
            $table->json('tags')->nullable();
            $table->boolean('is_system')->default(false); // system-provided vs user-created
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['brand_id', 'type']);
            $table->index(['platform', 'type']);
            $table->index('is_system');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_design_templates');
    }
};
