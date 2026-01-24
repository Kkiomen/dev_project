<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_approvals', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('social_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_token_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_approved')->nullable();
            $table->text('feedback_notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['social_post_id', 'approval_token_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_approvals');
    }
};
