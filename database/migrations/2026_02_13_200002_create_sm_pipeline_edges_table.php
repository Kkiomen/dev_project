<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_pipeline_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('sm_pipelines')->cascadeOnDelete();
            $table->string('edge_id')->comment('Client-side Vue Flow edge ID');
            $table->string('source_node_id');
            $table->string('source_handle')->nullable();
            $table->string('target_node_id');
            $table->string('target_handle')->nullable();
            $table->timestamps();

            $table->unique(['pipeline_id', 'edge_id']);
            $table->index(['pipeline_id', 'source_node_id']);
            $table->index(['pipeline_id', 'target_node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_pipeline_edges');
    }
};
