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
        Schema::create('psd_imports', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->string('filename');
            $table->string('file_hash', 64)->unique();
            $table->string('file_path');
            $table->unsignedBigInteger('file_size');
            $table->enum('status', ['pending', 'processing', 'ai_classifying', 'completed', 'failed'])
                ->default('pending');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psd_imports');
    }
};
