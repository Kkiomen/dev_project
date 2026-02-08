<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // bases: soft delete queries always filter deleted_at IS NULL
        Schema::table('bases', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index(['user_id', 'deleted_at']);
        });

        // tables: list tables for a base, always excluding soft-deleted
        Schema::table('tables', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index(['base_id', 'deleted_at']);
        });

        // fields: quick lookup of primary field per table
        Schema::table('fields', function (Blueprint $table) {
            $table->index(['table_id', 'is_primary']);
        });

        // rows: the most queried table — list rows for a table, excluding soft-deleted
        Schema::table('rows', function (Blueprint $table) {
            $table->index('deleted_at');
            $table->index(['table_id', 'deleted_at']);
        });

        // cells: text value lookups (Get Row by field value, Upsert matching)
        Schema::table('cells', function (Blueprint $table) {
            $table->rawIndex('`field_id`, `value_text`(255)', 'cells_field_id_value_text_index');
        });

        // attachments: quick lookup by mime type within a cell
        Schema::table('attachments', function (Blueprint $table) {
            $table->index('cell_id');
        });
    }

    public function down(): void
    {
        Schema::table('bases', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['user_id', 'deleted_at']);
        });

        Schema::table('tables', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['base_id', 'deleted_at']);
        });

        Schema::table('fields', function (Blueprint $table) {
            $table->dropIndex(['table_id', 'is_primary']);
        });

        Schema::table('rows', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['table_id', 'deleted_at']);
        });

        Schema::table('cells', function (Blueprint $table) {
            $table->dropIndex('cells_field_id_value_text_index');
        });

        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['cell_id']);
        });
    }
};
