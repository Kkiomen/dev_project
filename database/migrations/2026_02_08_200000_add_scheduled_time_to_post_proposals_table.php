<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_proposals', function (Blueprint $table) {
            $table->string('scheduled_time', 5)->nullable()->after('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::table('post_proposals', function (Blueprint $table) {
            $table->dropColumn('scheduled_time');
        });
    }
};
