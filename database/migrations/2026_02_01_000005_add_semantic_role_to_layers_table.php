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
        Schema::table('layers', function (Blueprint $table) {
            $table->string('semantic_role', 32)
                ->nullable()
                ->after('properties');
            $table->float('ai_confidence')
                ->nullable()
                ->after('semantic_role');

            $table->index('semantic_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->dropIndex(['semantic_role']);
            $table->dropColumn(['semantic_role', 'ai_confidence']);
        });
    }
};
