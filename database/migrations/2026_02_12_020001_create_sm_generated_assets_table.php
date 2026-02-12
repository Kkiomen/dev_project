<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_generated_assets', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_post_id')->nullable()->constrained('social_posts')->nullOnDelete();
            $table->unsignedBigInteger('sm_design_template_id')->nullable();
            $table->string('type'); // image, video, carousel_slide, thumbnail
            $table->string('file_path');
            $table->string('thumbnail_path')->nullable();
            $table->string('disk')->default('public');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('generation_prompt')->nullable();
            $table->string('ai_provider')->nullable(); // openai, wavespeed
            $table->string('ai_model')->nullable(); // dall-e-3, flux
            $table->json('generation_params')->nullable();
            $table->string('status')->default('pending'); // pending, generating, completed, failed
            $table->text('error_message')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('sm_design_template_id')
                ->references('id')
                ->on('sm_design_templates')
                ->nullOnDelete();

            $table->index(['brand_id', 'status']);
            $table->index(['social_post_id', 'position']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_generated_assets');
    }
};
