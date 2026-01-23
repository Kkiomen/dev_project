<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained()->cascadeOnDelete();
            $table->foreignId('field_id')->constrained()->cascadeOnDelete();

            // Wartości dla różnych typów - tylko jedna kolumna będzie używana
            $table->text('value_text')->nullable();           // text, url
            $table->decimal('value_number', 20, 6)->nullable(); // number
            $table->dateTime('value_datetime')->nullable();   // date
            $table->boolean('value_boolean')->nullable();     // checkbox
            $table->json('value_json')->nullable();           // select, multi_select, json, attachment

            $table->timestamps();

            $table->unique(['row_id', 'field_id']);
            $table->index(['field_id', 'value_number']);
            $table->index(['field_id', 'value_datetime']);
            $table->index(['field_id', 'value_boolean']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cells');
    }
};
