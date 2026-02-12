<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_comments', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('external_post_id')->nullable();
            $table->string('external_comment_id')->nullable();
            $table->foreignId('social_post_id')->nullable()->constrained('social_posts')->nullOnDelete();
            $table->string('author_handle')->nullable();
            $table->string('author_name')->nullable();
            $table->string('author_avatar')->nullable();
            $table->text('text');
            $table->string('sentiment')->nullable(); // positive, neutral, negative, crisis
            $table->boolean('is_replied')->default(false);
            $table->text('reply_text')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'platform']);
            $table->index(['brand_id', 'is_replied']);
            $table->index(['brand_id', 'sentiment']);
            $table->index('social_post_id');
        });

        Schema::create('sm_messages', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('external_thread_id')->nullable();
            $table->string('from_handle');
            $table->string('from_name')->nullable();
            $table->string('from_avatar')->nullable();
            $table->text('text');
            $table->string('direction')->default('inbound'); // inbound, outbound
            $table->boolean('is_read')->default(false);
            $table->boolean('auto_replied')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'platform']);
            $table->index(['brand_id', 'is_read']);
            $table->index('external_thread_id');
        });

        Schema::create('sm_auto_reply_rules', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_type'); // keyword, sentiment, question, all
            $table->string('trigger_value')->nullable(); // keyword or sentiment value
            $table->text('response_template');
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('usage_count')->default(0);
            $table->timestamps();

            $table->index(['brand_id', 'is_active']);
            $table->index('trigger_type');
        });

        Schema::create('sm_engagement_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('action_type'); // reply, like, hide, flag, escalate
            $table->string('target_type'); // comment, message
            $table->unsignedBigInteger('target_id');
            $table->text('content')->nullable();
            $table->string('status')->default('completed'); // pending_approval, completed, rejected
            $table->string('performed_by')->default('ai'); // ai, user
            $table->timestamps();

            $table->index(['brand_id', 'action_type']);
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('sm_crisis_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 26)->unique();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('severity'); // low, medium, high, critical
            $table->string('trigger_type'); // negative_spike, keyword, manual
            $table->text('description');
            $table->json('related_items')->nullable(); // [{type, id, text}]
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['brand_id', 'is_resolved']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_crisis_alerts');
        Schema::dropIfExists('sm_engagement_actions');
        Schema::dropIfExists('sm_auto_reply_rules');
        Schema::dropIfExists('sm_messages');
        Schema::dropIfExists('sm_comments');
    }
};
