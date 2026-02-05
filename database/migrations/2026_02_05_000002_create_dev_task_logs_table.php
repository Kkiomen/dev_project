<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_task_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('dev_task_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['bot_trigger', 'bot_response', 'plan_generation', 'status_change', 'comment']);
            $table->text('content')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('success')->default(true);
            $table->timestamps();

            $table->index(['dev_task_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_task_logs');
    }
};
