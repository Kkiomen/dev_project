<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_projects', function (Blueprint $table) {
            $table->json('composition')->nullable()->after('video_metadata');
            $table->boolean('is_template')->default(false)->after('composition');
            $table->string('template_name')->nullable()->after('is_template');
            $table->json('template_defaults')->nullable()->after('template_name');
        });
    }

    public function down(): void
    {
        Schema::table('video_projects', function (Blueprint $table) {
            $table->dropColumn(['composition', 'is_template', 'template_name', 'template_defaults']);
        });
    }
};
