<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // text, number, date, checkbox, select, multi_select, attachment, url, json
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('width')->default(200);
            $table->timestamps();

            $table->index(['table_id', 'position']);
            $table->index(['table_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
