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
            // Opacity: 0.0 (fully transparent) to 1.0 (fully opaque)
            $table->decimal('opacity', 4, 3)->default(1)->after('scale_y');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layers', function (Blueprint $table) {
            $table->dropColumn('opacity');
        });
    }
};
