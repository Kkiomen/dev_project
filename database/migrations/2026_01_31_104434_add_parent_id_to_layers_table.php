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
            // Parent layer ID for groups/folders hierarchy
            $table->foreignId('parent_id')
                ->nullable()
                ->after('template_id')
                ->constrained('layers')
                ->nullOnDelete();

            // Add index for faster tree queries
            $table->index(['template_id', 'parent_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['template_id', 'parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
