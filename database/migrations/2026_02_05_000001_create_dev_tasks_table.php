<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dev_tasks', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('identifier', 20)->unique();
            $table->string('project', 50);
            $table->unsignedInteger('sequence_number');
            $table->string('title');
            $table->text('pm_description')->nullable();
            $table->text('tech_description')->nullable();
            $table->text('implementation_plan')->nullable();
            $table->enum('status', ['backlog', 'in_progress', 'review', 'done'])->default('backlog');
            $table->unsignedInteger('position')->default(0);
            $table->string('priority', 20)->default('medium');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->json('labels')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('estimated_hours')->nullable();
            $table->unsignedInteger('actual_hours')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'position']);
            $table->index(['project', 'sequence_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dev_tasks');
    }
};
