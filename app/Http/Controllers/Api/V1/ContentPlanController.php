<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateContentPlanJob;
use App\Jobs\GeneratePostContentJob;
use App\Models\Brand;
use App\Models\SocialPost;
use App\Services\AI\ContentGeneratorService;
use App\Services\AI\ContentPlannerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentPlanController extends Controller
{
    public function __construct(
        protected ContentPlannerService $contentPlanner,
        protected ContentGeneratorService $contentGenerator
    ) {}

    /**
     * Generate a content plan for the specified period.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'brand_id' => ['required', 'string', 'exists:brands,public_id'],
            'period' => ['required', 'string', 'in:week,month'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'async' => ['nullable', 'boolean'],
        ]);

        $brand = Brand::findByPublicIdOrFail($request->brand_id);
        $this->authorize('update', $brand);

        $startDate = $request->start_date
            ? Carbon::parse($request->start_date)
            : Carbon::tomorrow();

        // If async, dispatch a job and return immediately
        if ($request->boolean('async', false)) {
            GenerateContentPlanJob::dispatch($brand, $startDate, $request->period);

            return response()->json([
                'message' => 'Content plan generation started',
                'status' => 'processing',
            ], 202);
        }

        // Otherwise, generate synchronously
        try {
            $plan = $request->period === 'month'
                ? $this->contentPlanner->generateMonthlyPlan($brand, $startDate)
                : $this->contentPlanner->generateWeeklyPlan($brand, $startDate);

            // Convert plan to draft posts
            $posts = $this->createDraftPosts($brand, $plan);

            return response()->json([
                'message' => 'Content plan generated successfully',
                'posts_count' => count($posts),
                'plan' => $plan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate content plan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate full content for a planned post.
     */
    public function generateContent(Request $request): JsonResponse
    {
        $request->validate([
            'brand_id' => ['required', 'string', 'exists:brands,public_id'],
            'pillar' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:1000'],
            'platforms' => ['required', 'array'],
            'platforms.*' => ['string', 'in:facebook,instagram,youtube'],
            'type' => ['nullable', 'string', 'in:text,carousel,video,story,reel,short'],
            'custom_instructions' => ['nullable', 'string', 'max:1000'],
            'post_id' => ['nullable', 'string', 'exists:social_posts,public_id'],
        ]);

        $brand = Brand::findByPublicIdOrFail($request->brand_id);
        $this->authorize('update', $brand);

        try {
            // Get user settings for AI generation
            $userSettings = $request->user()?->settings ?? [];

            $content = $this->contentGenerator->generate($brand, [
                'pillar' => $request->pillar,
                'topic' => $request->topic,
                'platforms' => $request->platforms,
                'type' => $request->type ?? 'text',
                'custom_instructions' => $request->custom_instructions,
            ], $userSettings);

            // If post_id provided, update the post
            if ($request->post_id) {
                $post = SocialPost::findByPublicIdOrFail($request->post_id);
                $this->authorize('update', $post);

                $post->update([
                    'title' => $content['title'],
                    'main_caption' => $content['main_caption'],
                ]);

                // Update platform-specific content
                $this->updatePlatformContent($post, $content);
            }

            return response()->json([
                'message' => 'Content generated successfully',
                'content' => $content,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate content',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerate content with feedback.
     */
    public function regenerateContent(Request $request): JsonResponse
    {
        $request->validate([
            'brand_id' => ['required', 'string', 'exists:brands,public_id'],
            'pillar' => ['required', 'string', 'max:255'],
            'topic' => ['required', 'string', 'max:1000'],
            'platforms' => ['required', 'array'],
            'platforms.*' => ['string', 'in:facebook,instagram,youtube'],
            'feedback' => ['required', 'string', 'max:1000'],
        ]);

        $brand = Brand::findByPublicIdOrFail($request->brand_id);
        $this->authorize('update', $brand);

        try {
            // Get user settings for AI generation
            $userSettings = $request->user()?->settings ?? [];

            $content = $this->contentGenerator->regenerate($brand, [
                'pillar' => $request->pillar,
                'topic' => $request->topic,
                'platforms' => $request->platforms,
            ], $request->feedback, $userSettings);

            return response()->json([
                'message' => 'Content regenerated successfully',
                'content' => $content,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to regenerate content',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create draft posts from a content plan.
     */
    protected function createDraftPosts(Brand $brand, array $plan): array
    {
        $posts = [];

        foreach ($plan['posts'] as $item) {
            $scheduledAt = Carbon::parse("{$item['date']} {$item['time']}");

            $post = $brand->posts()->create([
                'user_id' => $brand->user_id,
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
     * Update platform-specific content on a post.
     */
    protected function updatePlatformContent(SocialPost $post, array $content): void
    {
        if (empty($content['platforms'])) {
            return;
        }

        foreach ($content['platforms'] as $platform => $platformContent) {
            $platformPost = $post->platformPosts()->where('platform', $platform)->first();

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
}
