<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_fonts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->string('font_family', 100);
            $table->string('font_file');
            $table->string('font_weight', 20)->default('normal');
            $table->string('font_style', 20)->default('normal');
            $table->timestamps();

            $table->index('template_id');
            $table->unique(['template_id', 'font_family', 'font_weight', 'font_style'], 'tpl_fonts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_fonts');
    }
};
