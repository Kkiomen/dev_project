<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_cost_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('total_cost', 8, 4)->default(0);
            $table->decimal('budget_limit', 8, 2)->default(5.00);
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('total_results')->default(0);
            $table->json('cost_breakdown')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ci_cost_tracking');
    }
};
