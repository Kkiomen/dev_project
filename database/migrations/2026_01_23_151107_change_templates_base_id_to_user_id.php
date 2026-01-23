<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add user_id column
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Migrate existing data: copy user_id from base
        DB::table('templates')
            ->whereNotNull('base_id')
            ->update([
                'user_id' => DB::raw('(SELECT user_id FROM bases WHERE bases.id = templates.base_id)'),
            ]);

        // Make user_id required and base_id nullable
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreignId('base_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('base_id')->nullable(false)->change();
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
