<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_content_templates', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category'); // hook, cta, caption, thread, carousel_script, reel_script
            $table->string('platform')->nullable(); // null = all platforms
            $table->text('prompt_template'); // AI prompt with {{variables}}
            $table->json('variables')->nullable(); // [{name, type, default, description}]
            $table->string('content_type')->nullable(); // text, carousel, video, poll
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['brand_id', 'category']);
            $table->index(['platform', 'category']);
            $table->index('is_system');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_content_templates');
    }
};
