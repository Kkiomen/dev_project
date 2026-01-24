<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_posts', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('publish_status');
            $table->text('error_message')->nullable()->after('external_id');
            $table->json('platform_data')->nullable()->after('error_message');
        });

        // Update default publish_status to not_started
        DB::table('platform_posts')
            ->where('publish_status', 'pending')
            ->whereNull('published_at')
            ->update(['publish_status' => 'not_started']);
    }

    public function down(): void
    {
        Schema::table('platform_posts', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'error_message', 'platform_data']);
        });

        // Revert publish_status
        DB::table('platform_posts')
            ->where('publish_status', 'not_started')
            ->update(['publish_status' => 'pending']);
    }
};
