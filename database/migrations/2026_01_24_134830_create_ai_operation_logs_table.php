<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('operation'); // 'plan_generation', 'content_generation', 'regeneration'
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->decimal('cost', 10, 6)->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status')->default('pending'); // 'pending', 'completed', 'failed'
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'operation']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_operation_logs');
    }
};
