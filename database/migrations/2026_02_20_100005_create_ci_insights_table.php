<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_insights', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('insight_type', 50);
            $table->string('platform', 50)->nullable();
            $table->string('title');
            $table->text('description');
            $table->json('data')->nullable();
            $table->unsignedTinyInteger('priority')->default(5);
            $table->boolean('is_actioned')->default(false);
            $table->string('action_taken')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'is_actioned', 'priority']);
            $table->index(['brand_id', 'insight_type']);
            $table->index(['brand_id', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_insights');
    }
};
