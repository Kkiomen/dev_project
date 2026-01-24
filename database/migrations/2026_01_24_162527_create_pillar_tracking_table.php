<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pillar_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('pillar_name');
            $table->integer('week_number');
            $table->integer('year');
            $table->integer('planned_count')->default(0);
            $table->integer('published_count')->default(0);
            $table->integer('target_percentage');
            $table->timestamps();

            $table->unique(['brand_id', 'pillar_name', 'week_number', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pillar_tracking');
    }
};
