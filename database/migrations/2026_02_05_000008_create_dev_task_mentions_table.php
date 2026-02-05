<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_task_mentions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dev_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('log_id')->nullable()->constrained('dev_task_logs')->cascadeOnDelete();
            $table->boolean('notified')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['dev_task_id', 'user_id', 'log_id']);
            $table->index(['user_id', 'notified']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_task_mentions');
    }
};
