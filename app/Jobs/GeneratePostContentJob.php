<?php

namespace App\Jobs;

use App\Events\PostContentGenerated;
use App\Models\Brand;
use App\Models\SocialPost;
use App\Services\AI\ContentGeneratorService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GeneratePostContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 60;

    public int $tries = 3;

    public function __construct(
        protected Brand $brand,
        protected SocialPost $post,
        protected array $config
    ) {}

    protected function taskType(): string { return 'post_content_generation'; }
    protected function taskUserId(): int { return $this->brand->user_id; }
    protected function taskModelId(): string|int { return $this->post->id; }

    public function handle(ContentGeneratorService $generator): void
    {
        $this->broadcastTaskStarted();

        try {
            // Get user settings for AI generation
            $userSettings = $this->brand->user?->settings ?? [];

            // Generate the content
            $content = $generator->generate($this->brand, $this->config, $userSettings);

            // Update the post
            $this->post->update([
                'title' => $content['title'],
                'main_caption' => $content['main_caption'],
            ]);

            // Update platform-specific content
            $this->updatePlatformContent($content);

            // Broadcast event
            broadcast(new PostContentGenerated($this->post, $content));

            Log::info('Post content generated', [
                'post_id' => $this->post->id,
                'brand_id' => $this->brand->id,
            ]);

            $this->broadcastTaskCompleted(true, null, [
                'post_id' => $this->post->public_id ?? $this->post->id,
                'brand_name' => $this->brand->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate post content', [
                'post_id' => $this->post->id,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Update platform-specific content on the post.
     */
    protected function updatePlatformContent(array $content): void
    {
        if (empty($content['platforms'])) {
            return;
        }

        foreach ($content['platforms'] as $platform => $platformContent) {
            $platformPost = $this->post->platformPosts()
                ->where('platform', $platform)
                ->first();

            if (!$platformPost) {
                continue;
            }

            $updateData = [];

            if (isset($platformContent['caption'])) {
                $updateData['platform_caption'] = $platformContent['caption'];
            }

            if (isset($platformContent['hashtags'])) {
                $updateData['hashtags'] = $platformContent['hashtags'];
            }

            if (isset($platformContent['title'])) {
                $updateData['youtube_title'] = $platformContent['title'];
            }

            if (isset($platformContent['description'])) {
                $updateData['platform_caption'] = $platformContent['description'];
            }

            if (!empty($updateData)) {
                $platformPost->update($updateData);
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Post content generation job failed', [
            'post_id' => $this->post->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
