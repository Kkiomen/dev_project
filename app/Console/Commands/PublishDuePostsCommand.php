<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Jobs\PublishPostJob;
use App\Models\SocialPost;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PublishDuePostsCommand extends Command
{
    protected $signature = 'posts:publish-due {--dry-run : Show posts that would be published without actually publishing}';

    protected $description = 'Publish posts that are scheduled and due';

    public function handle(): int
    {
        $now = Carbon::now();
        $isDryRun = $this->option('dry-run');

        // Find posts that are scheduled or approved with a scheduled_at time that has passed
        $duePosts = SocialPost::whereIn('status', [PostStatus::Scheduled, PostStatus::Approved])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $now)
            ->get();

        if ($duePosts->isEmpty()) {
            $this->info('No posts due for publishing.');
            return self::SUCCESS;
        }

        $this->info("Found {$duePosts->count()} posts due for publishing.");

        foreach ($duePosts as $post) {
            $scheduledAt = $post->scheduled_at->format('Y-m-d H:i:s');
            $this->line("- Post #{$post->id}: \"{$post->title}\" (scheduled: {$scheduledAt})");

            if (!$isDryRun) {
                PublishPostJob::dispatch($post);
                $this->info("  -> Job dispatched");
            }
        }

        if ($isDryRun) {
            $this->warn('Dry run mode - no jobs were dispatched.');
        } else {
            $this->info('All publish jobs dispatched successfully.');
        }

        return self::SUCCESS;
    }
}
