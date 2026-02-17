<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_pipeline_nodes', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('pipeline_id')->constrained('sm_pipelines')->cascadeOnDelete();
            $table->string('node_id')->comment('Client-side Vue Flow node ID');
            $table->string('type');
            $table->string('label')->nullable();
            $table->json('position');
            $table->json('config')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->unique(['pipeline_id', 'node_id']);
            $table->index(['pipeline_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_pipeline_nodes');
    }
};
