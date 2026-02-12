<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_brand_kits', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();

            // Visual identity
            $table->json('colors')->nullable();
            // Structure: {
            //     "primary": "#6366F1",
            //     "secondary": "#EC4899",
            //     "accent": "#F59E0B",
            //     "background": "#FFFFFF",
            //     "text": "#111827"
            // }

            $table->json('fonts')->nullable();
            // Structure: {
            //     "heading": {"family": "Inter", "weight": "700"},
            //     "body": {"family": "Inter", "weight": "400"},
            //     "accent": {"family": "Playfair Display", "weight": "600"}
            // }

            $table->string('logo_path')->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('style_preset')->nullable(); // modern, classic, bold, minimal, playful

            // Brand voice
            $table->string('tone_of_voice')->nullable(); // professional, casual, friendly, authoritative, humorous
            $table->json('voice_attributes')->nullable();
            // Structure: ["inspiring", "educational", "empathetic"]

            $table->json('content_pillars')->nullable();
            // Structure: [
            //     {"name": "Educational", "percentage": 40, "description": "Tips and how-tos"},
            //     {"name": "Behind the scenes", "percentage": 30, "description": "..."},
            //     {"name": "Promotional", "percentage": 30, "description": "..."}
            // ]

            $table->json('hashtag_groups')->nullable();
            // Structure: {
            //     "branded": ["#mybrand", "#mybrandlife"],
            //     "industry": ["#marketing", "#socialmedia"],
            //     "trending": []
            // }

            $table->text('brand_guidelines_notes')->nullable();
            $table->timestamps();

            $table->unique('brand_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_brand_kits');
    }
};
