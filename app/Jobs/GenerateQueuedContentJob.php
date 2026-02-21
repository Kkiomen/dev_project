<?php

namespace App\Jobs;

use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Events\CalendarPostCreated;
use App\Events\PostContentGenerated;
use App\Models\ContentQueue;
use App\Models\SocialPost;
use App\Services\AI\ContentGeneratorService;
use App\Services\NotificationService;
use App\Traits\BroadcastsTaskProgress;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateQueuedContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 120;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        protected ContentQueue $queueItem
    ) {}

    protected function taskType(): string { return 'content_generation'; }
    protected function taskUserId(): int { return $this->queueItem->brand->user_id; }
    protected function taskModelId(): string|int { return $this->queueItem->id; }
    protected function taskStartData(): array
    {
        $brand = $this->queueItem->brand;
        return [
            'pillar' => $this->queueItem->pillar_name,
            'platform' => $this->queueItem->platform,
            'brand_name' => $brand->name,
        ];
    }

    public function handle(ContentGeneratorService $generator): void
    {
        $brand = $this->queueItem->brand;

        $this->broadcastTaskStarted();

        try {
            // Generate topic if not set
            $topic = $this->queueItem->topic;
            if (empty($topic)) {
                $topic = $this->generateTopic();
            }

            // Get pillar details
            $pillar = $brand->getPillarByName($this->queueItem->pillar_name);

            // Build config for content generation
            $config = [
                'pillar' => $this->queueItem->pillar_name,
                'topic' => $topic,
                'type' => $this->queueItem->content_type,
                'platforms' => [$this->queueItem->platform],
            ];

            // Get user settings for AI generation
            $userSettings = $brand->user?->settings ?? [];

            // Generate the content using AI
            $content = $generator->generate($brand, $config, $userSettings);

            // Create the social post
            $post = $this->createSocialPost($brand, $content);

            // Mark queue item as ready
            $this->queueItem->markAsReady($post);

            // Update topic if it was generated
            if (empty($this->queueItem->topic)) {
                $this->queueItem->update(['topic' => $topic]);
            }

            // Broadcast events
            broadcast(new PostContentGenerated($post, $content));
            broadcast(new CalendarPostCreated($post));

            // Send notification to user
            $notificationService = app(NotificationService::class);
            $notificationService->postGenerated(
                $brand->user,
                $content['title'],
                $brand->name,
                $post->public_id
            );

            $this->broadcastTaskCompleted(true, null, [
                'post_id' => $post->public_id,
                'post_title' => $content['title'],
                'brand_name' => $brand->name,
            ]);

            Log::info('Queued content generated successfully', [
                'queue_id' => $this->queueItem->id,
                'post_id' => $post->id,
                'brand_id' => $brand->id,
            ]);
        } catch (\Exception $e) {
            $this->queueItem->markAsFailed($e->getMessage());

            $this->broadcastTaskCompleted(false, $e->getMessage(), [
                'pillar' => $this->queueItem->pillar_name,
                'brand_name' => $brand->name,
            ]);

            Log::error('Failed to generate queued content', [
                'queue_id' => $this->queueItem->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a social post from generated content.
     */
    protected function createSocialPost($brand, array $content): SocialPost
    {
        $scheduledAt = $this->queueItem->getScheduledDateTime();

        // Create the post
        $post = SocialPost::create([
            'user_id' => $brand->user_id,
            'brand_id' => $brand->id,
            'title' => $content['title'],
            'main_caption' => $content['main_caption'],
            'status' => PostStatus::Draft,
            'scheduled_at' => $scheduledAt,
            'settings' => [
                'planned_pillar' => $this->queueItem->pillar_name,
                'auto_generated' => true,
                'queue_id' => $this->queueItem->id,
                'image_keywords' => $content['image_keywords'] ?? [],
            ],
        ]);

        // Create platform posts for ALL enabled platforms in the brand
        $enabledPlatforms = $brand->getEnabledPlatforms();
        $targetPlatform = Platform::from($this->queueItem->platform);

        foreach ($enabledPlatforms as $platform) {
            $platformPost = $post->platformPosts()->create([
                'platform' => $platform->value,
                'enabled' => true,
            ]);

            // Set platform-specific content from AI generation
            $platformContent = $content['platforms'][$platform->value] ?? [];

            if (!empty($platformContent)) {
                $updateData = [];

                if (isset($platformContent['caption'])) {
                    $updateData['platform_caption'] = $platformContent['caption'];
                }

                if (isset($platformContent['hashtags'])) {
                    $updateData['hashtags'] = $platformContent['hashtags'];
                }

                if (isset($platformContent['title']) && $platform === Platform::YouTube) {
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

        return $post->load('platformPosts');
    }

    /**
     * Generate a topic based on the pillar.
     */
    protected function generateTopic(): string
    {
        $brand = $this->queueItem->brand;
        $pillar = $brand->getPillarByName($this->queueItem->pillar_name);

        if ($pillar) {
            $description = $pillar['description'] ?? '';
            return !empty($description) ? $description : $this->queueItem->pillar_name;
        }

        return $this->queueItem->pillar_name;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Generate queued content job failed permanently', [
            'queue_id' => $this->queueItem->id,
            'error' => $exception->getMessage(),
        ]);

        $this->queueItem->markAsFailed($exception->getMessage());
    }
}
