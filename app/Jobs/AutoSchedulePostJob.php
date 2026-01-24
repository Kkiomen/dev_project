<?php

namespace App\Jobs;

use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Models\SocialPost;
use App\Services\Automation\ScheduleSlotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoSchedulePostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public function __construct(
        protected SocialPost $post
    ) {}

    public function handle(ScheduleSlotService $slotService): void
    {
        // Skip if post already has a scheduled time
        if ($this->post->scheduled_at) {
            Log::info('Post already scheduled, skipping auto-schedule', [
                'post_id' => $this->post->id,
            ]);
            return;
        }

        // Skip if brand doesn't have auto-schedule enabled
        $brand = $this->post->brand;
        if (!$brand || !$brand->hasAutoSchedule()) {
            Log::info('Auto-schedule not enabled for brand, skipping', [
                'post_id' => $this->post->id,
            ]);
            return;
        }

        // Get the first enabled platform for this post
        $enabledPlatforms = $this->post->getEnabledPlatforms();
        if (empty($enabledPlatforms)) {
            Log::info('No enabled platforms for post, skipping auto-schedule', [
                'post_id' => $this->post->id,
            ]);
            return;
        }

        // Use the first enabled platform to find the next slot
        $platform = Platform::from($enabledPlatforms[0]);
        $nextSlot = $slotService->getNextAvailableSlot($brand, $platform);

        if (!$nextSlot) {
            Log::warning('No available slot found for auto-schedule', [
                'post_id' => $this->post->id,
                'platform' => $platform->value,
            ]);
            return;
        }

        // Schedule the post
        $scheduledAt = $nextSlot['datetime'];
        $this->post->scheduled_at = $scheduledAt;

        // If post is approved, set to scheduled status
        if ($this->post->status === PostStatus::Approved) {
            $this->post->status = PostStatus::Scheduled;
        }

        $this->post->save();

        Log::info('Post auto-scheduled successfully', [
            'post_id' => $this->post->id,
            'scheduled_at' => $scheduledAt->toIso8601String(),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Auto-schedule post job failed', [
            'post_id' => $this->post->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
