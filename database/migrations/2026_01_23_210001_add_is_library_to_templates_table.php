<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->boolean('is_library')->default(false)->after('user_id');
            $table->string('library_category')->nullable()->after('is_library');
            $table->index('is_library');
        });
    }

    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropIndex(['is_library']);
            $table->dropColumn(['is_library', 'library_category']);
        });
    }
};
