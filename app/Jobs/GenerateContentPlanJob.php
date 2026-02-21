<?php

namespace App\Jobs;

use App\Events\ContentPlanGenerated;
use App\Models\Brand;
use App\Models\SocialPost;
use App\Services\AI\ContentPlannerService;
use App\Traits\BroadcastsTaskProgress;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateContentPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BroadcastsTaskProgress;

    public int $timeout = 120;

    public int $tries = 3;

    public function __construct(
        protected Brand $brand,
        protected Carbon $startDate,
        protected string $period
    ) {}

    protected function taskType(): string { return 'content_plan_generation'; }
    protected function taskUserId(): int { return $this->brand->user_id; }
    protected function taskModelId(): string|int { return $this->brand->id; }

    public function handle(ContentPlannerService $planner): void
    {
        $this->broadcastTaskStarted();

        try {
            // Generate the plan
            $plan = $this->period === 'month'
                ? $planner->generateMonthlyPlan($this->brand, $this->startDate)
                : $planner->generateWeeklyPlan($this->brand, $this->startDate);

            // Create draft posts from the plan
            $posts = $this->createDraftPosts($plan);

            // Broadcast event
            broadcast(new ContentPlanGenerated($this->brand, $plan, $posts));

            Log::info('Content plan generated', [
                'brand_id' => $this->brand->id,
                'period' => $this->period,
                'posts_count' => count($posts),
            ]);

            $this->broadcastTaskCompleted(true, null, [
                'brand_name' => $this->brand->name,
                'posts_count' => count($posts),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate content plan', [
                'brand_id' => $this->brand->id,
                'error' => $e->getMessage(),
            ]);

            $this->broadcastTaskCompleted(false, $e->getMessage());

            throw $e;
        }
    }

    /**
     * Create draft posts from the generated plan.
     */
    protected function createDraftPosts(array $plan): array
    {
        $posts = [];

        foreach ($plan['posts'] as $item) {
            $scheduledAt = Carbon::parse("{$item['date']} {$item['time']}");

            $post = $this->brand->posts()->create([
                'user_id' => $this->brand->user_id,
                'title' => $item['topic'],
                'main_caption' => '',
                'status' => 'draft',
                'scheduled_at' => $scheduledAt,
                'settings' => [
                    'planned_pillar' => $item['pillar'],
                    'planned_type' => $item['type'],
                    'planned_hook' => $item['hook'] ?? null,
                    'planned_platform' => $item['platform'],
                ],
            ]);

            // Create platform posts
            $post->createPlatformPosts();

            // Enable only the planned platform
            $post->platformPosts()->update(['enabled' => false]);
            $post->platformPosts()
                ->where('platform', $item['platform'])
                ->update(['enabled' => true]);

            $posts[] = $post;
        }

        return $posts;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Content plan generation job failed', [
            'brand_id' => $this->brand->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
