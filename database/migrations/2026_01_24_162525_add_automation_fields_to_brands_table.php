<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->boolean('automation_enabled')->default(false)->after('is_active');
            $table->integer('content_queue_days')->default(7)->after('automation_enabled');
            $table->json('automation_settings')->nullable()->after('content_queue_days');
            $table->timestamp('last_automation_run')->nullable()->after('automation_settings');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn([
                'automation_enabled',
                'content_queue_days',
                'automation_settings',
                'last_automation_run',
            ]);
        });
    }
};
