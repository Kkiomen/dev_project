<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rows', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('table_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['table_id', 'position']);
            $table->index(['table_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rows');
    }
};
