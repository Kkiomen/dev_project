<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_publish_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sm_scheduled_post_id');
            $table->string('action'); // publish, retry, callback, error
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->foreign('sm_scheduled_post_id')
                ->references('id')
                ->on('sm_scheduled_posts')
                ->cascadeOnDelete();

            $table->index('sm_scheduled_post_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_publish_logs');
    }
};
