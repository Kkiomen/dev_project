<?php

namespace App\Jobs\SmManager;

use App\Models\SmContentPlanSlot;
use App\Models\SmGeneratedAsset;
use App\Models\SocialPost;
use App\Services\AI\DirectImageGeneratorService;
use App\Services\AI\DirectTextGeneratorService;
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
use Illuminate\Support\Facades\Storage;

class SmGeneratePostContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected SmContentPlanSlot $slot
    ) {}

    public function handle(
        SmCopywriterService $copywriter,
        SmHashtagService $hashtagService,
        SmSchedulerService $scheduler,
        DirectTextGeneratorService $textGenerator,
        DirectImageGeneratorService $imageGenerator,
    ): void {
        try {
            // Refresh slot from DB to get current status (may have been reset while queued)
            $this->slot->refresh();

            if ($this->slot->status !== 'generating') {
                Log::info('SmGeneratePostContentJob: skipping — slot no longer in generating status', [
                    'slot_id' => $this->slot->id,
                    'current_status' => $this->slot->status,
                ]);
                return;
            }

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
                'title' => str_replace('—', '-', $this->slot->topic),
                'main_caption' => str_replace('—', '-', $copyResult['text']),
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

            // Update slot with the generated social post reference (before image, so frontend sees progress)
            $this->slot->update([
                'social_post_id' => $post->id,
                'status' => 'content_ready',
            ]);

            // Auto-schedule the post
            $scheduledAt = $this->slot->getScheduledDateTime()
                ? Carbon::parse($this->slot->getScheduledDateTime())
                : null;

            $scheduler->schedulePost($brand, $post->id, $this->slot->platform, $scheduledAt);

            // Generate image (non-blocking — failure does not fail the job, runs after slot is ready)
            $this->generateImage($post, $brand, $textGenerator, $imageGenerator);

            Log::info('SmGeneratePostContentJob: post content generated and scheduled', [
                'slot_id' => $this->slot->id,
                'social_post_id' => $post->id,
                'brand_id' => $brand->id,
                'platform' => $this->slot->platform,
                'hashtags_count' => count($hashtags),
                'has_image' => $post->image_prompt !== null,
            ]);
        } catch (\Exception $e) {
            Log::error('SmGeneratePostContentJob: failed', [
                'slot_id' => $this->slot->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate image prompt and image for the post. Failures are logged but do not fail the job.
     */
    protected function generateImage(
        SocialPost $post,
        \App\Models\Brand $brand,
        DirectTextGeneratorService $textGenerator,
        DirectImageGeneratorService $imageGenerator,
    ): void {
        try {
            // Step 1: Generate image prompt via GPT-4o
            $promptResult = $textGenerator->generateImageDescription($post, $this->slot->content_type);

            if (!$promptResult['success']) {
                Log::warning('SmGeneratePostContentJob: image prompt generation failed, skipping image', [
                    'post_id' => $post->id,
                    'error' => $promptResult['error'] ?? 'Unknown',
                ]);
                return;
            }

            $post->update(['image_prompt' => $promptResult['image_prompt']]);

            // Step 2: Generate image via WaveSpeed
            $imageResult = $imageGenerator->generate($post);

            if (!$imageResult['success']) {
                Log::warning('SmGeneratePostContentJob: image generation failed, post continues without image', [
                    'post_id' => $post->id,
                    'error' => $imageResult['error'] ?? 'Unknown',
                ]);
                return;
            }

            // Step 3: Save image to storage
            $filename = $imageResult['filename'];
            $path = "brands/{$brand->id}/generated/{$filename}";
            Storage::disk('public')->put($path, $imageResult['image_data']);

            // Step 4: Create SmGeneratedAsset record
            SmGeneratedAsset::create([
                'brand_id' => $brand->id,
                'social_post_id' => $post->id,
                'type' => 'image',
                'file_path' => $path,
                'disk' => 'public',
                'mime_type' => $imageResult['mime_type'],
                'file_size' => strlen($imageResult['image_data']),
                'generation_prompt' => $promptResult['image_prompt'],
                'ai_provider' => 'wavespeed',
                'ai_model' => 'nano-banana',
                'status' => 'completed',
                'position' => 0,
            ]);

            Log::info('SmGeneratePostContentJob: image generated successfully', [
                'post_id' => $post->id,
                'path' => $path,
            ]);
        } catch (\Throwable $e) {
            Log::warning('SmGeneratePostContentJob: image pipeline error, post continues without image', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SmGeneratePostContentJob: job failed permanently', [
            'slot_id' => $this->slot->id,
            'error' => $exception->getMessage(),
        ]);

        // Only reset to planned if slot is still in generating status
        // Prevents race condition when a new generation dispatch has already claimed the slot
        SmContentPlanSlot::where('id', $this->slot->id)
            ->where('status', 'generating')
            ->update(['status' => 'planned']);
    }
}
