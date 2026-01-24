<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_operation_logs', function (Blueprint $table) {
            $table->string('provider')->default('openai')->after('operation');
            $table->string('model')->nullable()->after('provider');
            $table->uuid('request_id')->nullable()->after('model');
            $table->string('endpoint')->nullable()->after('request_id');
            $table->unsignedInteger('prompt_tokens')->nullable()->after('tokens_used');
            $table->unsignedInteger('completion_tokens')->nullable()->after('prompt_tokens');
            $table->unsignedSmallInteger('http_status')->nullable()->after('completion_tokens');

            $table->index('provider');
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_operation_logs', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropIndex(['request_id']);

            $table->dropColumn([
                'provider',
                'model',
                'request_id',
                'endpoint',
                'prompt_tokens',
                'completion_tokens',
                'http_status',
            ]);
        });
    }
};
