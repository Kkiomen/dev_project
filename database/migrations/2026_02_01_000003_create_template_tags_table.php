<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('template_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('name_pl')->nullable();
            $table->enum('category', ['style', 'mood', 'color', 'layout']);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index('category');
            $table->index('usage_count');
        });

        // Pivot table for template-tag relationship
        Schema::create('template_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_tag_id')->constrained()->onDelete('cascade');
            $table->float('confidence')->default(1.0);
            $table->boolean('is_ai_generated')->default(false);
            $table->timestamps();

            $table->unique(['template_id', 'template_tag_id']);
            $table->index('template_tag_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_tag');
        Schema::dropIfExists('template_tags');
    }
};
