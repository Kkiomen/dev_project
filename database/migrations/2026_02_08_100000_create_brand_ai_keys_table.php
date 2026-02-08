<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brand_ai_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // openai, gemini, wavespeed
            $table->text('api_key'); // encrypted
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['brand_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_ai_keys');
    }
};
