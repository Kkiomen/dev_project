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
        Schema::table('templates', function (Blueprint $table) {
            $table->foreignId('template_group_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
            $table->unsignedTinyInteger('variant_order')
                ->nullable()
                ->after('template_group_id');
            $table->foreignId('psd_import_id')
                ->nullable()
                ->after('variant_order')
                ->constrained()
                ->nullOnDelete();

            $table->index('template_group_id');
            $table->index('psd_import_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_group_id');
            $table->dropColumn('variant_order');
            $table->dropConstrainedForeignId('psd_import_id');
        });
    }
};
