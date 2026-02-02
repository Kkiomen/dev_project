<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psd_layer_tags', function (Blueprint $table) {
            $table->id();
            $table->string('psd_filename', 255);
            $table->string('layer_path', 500);
            $table->string('semantic_tag', 50)->nullable();
            $table->boolean('is_variant')->default(false);
            $table->timestamps();

            $table->unique(['psd_filename', 'layer_path'], 'unique_layer');
            $table->index('psd_filename');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psd_layer_tags');
    }
};
