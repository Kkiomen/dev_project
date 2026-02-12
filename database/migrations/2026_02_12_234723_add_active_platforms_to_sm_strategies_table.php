<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sm_strategies', function (Blueprint $table) {
            $table->json('active_platforms')->nullable()->after('brand_id');
        });
    }

    public function down(): void
    {
        Schema::table('sm_strategies', function (Blueprint $table) {
            $table->dropColumn('active_platforms');
        });
    }
};
