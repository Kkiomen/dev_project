<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('pillar_name');
            $table->string('platform');
            $table->date('target_date');
            $table->string('target_time')->nullable();
            $table->string('topic')->nullable();
            $table->string('content_type')->default('text');
            $table->enum('status', ['pending', 'generating', 'ready', 'published', 'failed'])->default('pending');
            $table->foreignId('social_post_id')->nullable()->constrained()->nullOnDelete();
            $table->text('generation_error')->nullable();
            $table->integer('generation_attempts')->default(0);
            $table->timestamps();

            $table->index(['brand_id', 'status']);
            $table->index(['brand_id', 'target_date']);
            $table->unique(['brand_id', 'platform', 'target_date', 'target_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_queue');
    }
};
