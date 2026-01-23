<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('layers', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('layer_key', 100)->nullable();
            $table->string('name');
            $table->string('type'); // text, image, rectangle, ellipse
            $table->unsignedInteger('position')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('locked')->default(false);

            // Position and dimensions
            $table->decimal('x', 10, 2)->default(0);
            $table->decimal('y', 10, 2)->default(0);
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('rotation', 5, 2)->default(0);
            $table->decimal('scale_x', 5, 3)->default(1);
            $table->decimal('scale_y', 5, 3)->default(1);

            // Type-specific properties stored as JSON
            $table->json('properties')->nullable();

            $table->timestamps();

            $table->index(['template_id', 'position']);
            $table->index(['template_id', 'type']);
            $table->index(['template_id', 'layer_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('layers');
    }
};
