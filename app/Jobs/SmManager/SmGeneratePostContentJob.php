<?php

namespace App\Jobs\SmManager;

use App\Models\SmContentPlanSlot;
use App\Models\SocialPost;
use App\Services\SmManager\SmCopywriterService;
use App\Services\SmManager\SmHashtagService;
use App\Services\SmManager\SmSchedulerService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmGeneratePostContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected SmContentPlanSlot $slot
    ) {}

    public function handle(SmCopywriterService $copywriter, SmHashtagService $hashtagService, SmSchedulerService $scheduler): void
    {
        try {
            $contentPlan = $this->slot->contentPlan;
            $brand = $contentPlan->brand;

            if (!$brand) {
                throw new \RuntimeException('No brand found for content plan slot');
            }

            // Generate post text via copywriter service
            $copyResult = $copywriter->generatePost($brand, [
                'platform' => $this->slot->platform,
                'content_type' => $this->slot->content_type,
                'topic' => $this->slot->topic,
                'pillar' => $this->slot->pillar,
                'description' => $this->slot->description,
            ]);

            if (!$copyResult['success']) {
                throw new \RuntimeException($copyResult['error'] ?? 'Copywriter generation failed');
            }

            // Generate hashtags via hashtag service
            $hashtagResult = $hashtagService->generate(
                $brand,
                $copyResult['text'],
                $this->slot->platform
            );

            $hashtags = $hashtagResult['success']
                ? $hashtagResult['hashtags']
                : ($copyResult['hashtags'] ?? []);

            // Create the SocialPost record
            $post = SocialPost::create([
                'user_id' => $brand->user_id,
                'brand_id' => $brand->id,
                'title' => $this->slot->topic,
                'main_caption' => $copyResult['text'],
                'status' => 'draft',
                'scheduled_at' => $this->slot->getScheduledDateTime(),
                'settings' => [
                    'hook' => $copyResult['hook'] ?? null,
                    'cta' => $copyResult['cta'] ?? null,
                    'hashtags' => $hashtags,
                    'pillar' => $this->slot->pillar,
                    'content_type' => $this->slot->content_type,
                    'platform' => $this->slot->platform,
                    'sm_content_plan_slot_id' => $this->slot->id,
                ],
            ]);

            // Update slot with the generated social post reference
            $this->slot->update([
                'social_post_id' => $post->id,
                'status' => 'content_ready',
            ]);

            // Auto-schedule the post
            $scheduledAt = $this->slot->getScheduledDateTime()
                ? Carbon::parse($this->slot->getScheduledDateTime())
                : null;

            $scheduler->schedulePost($brand, $post->id, $this->slot->platform, $scheduledAt);

            Log::info('SmGeneratePostContentJob: post content generated and scheduled', [
                'slot_id' => $this->slot->id,
                'social_post_id' => $post->id,
                'brand_id' => $brand->id,
                'platform' => $this->slot->platform,
                'hashtags_count' => count($hashtags),
            ]);
        } catch (\Exception $e) {
            Log::error('SmGeneratePostContentJob: failed', [
                'slot_id' => $this->slot->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SmGeneratePostContentJob: job failed permanently', [
            'slot_id' => $this->slot->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
