<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_trending_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 50)->nullable();
            $table->string('source', 100);
            $table->string('topic');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('volume')->default(0);
            $table->decimal('growth_rate', 8, 2)->default(0);
            $table->string('trend_direction', 20)->default('stable');
            $table->json('related_hashtags')->nullable();
            $table->date('valid_from');
            $table->date('valid_until');
            $table->timestamps();

            $table->index(['brand_id', 'valid_until']);
            $table->index(['brand_id', 'platform', 'trend_direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_trending_topics');
    }
};
