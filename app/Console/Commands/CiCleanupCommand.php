<?php

namespace App\Console\Commands;

use App\Models\CiCompetitorPost;
use App\Models\CiScrapeRun;
use App\Models\CiTrendingTopic;
use App\Models\CiInsight;
use Illuminate\Console\Command;

class CiCleanupCommand extends Command
{
    protected $signature = 'ci:cleanup {--days=90 : Retention period in days}';

    protected $description = 'Clean up old competitive intelligence data';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $this->info("Cleaning up CI data older than {$days} days...");

        $deletedPosts = CiCompetitorPost::where('posted_at', '<', $cutoff)->delete();
        $this->line("  - Deleted {$deletedPosts} old competitor posts");

        $deletedRuns = CiScrapeRun::where('created_at', '<', $cutoff)->delete();
        $this->line("  - Deleted {$deletedRuns} old scrape runs");

        $deletedTopics = CiTrendingTopic::where('valid_until', '<', now()->toDateString())->delete();
        $this->line("  - Deleted {$deletedTopics} expired trending topics");

        $deletedInsights = CiInsight::where('is_actioned', true)
            ->where('updated_at', '<', $cutoff)
            ->delete();
        $this->line("  - Deleted {$deletedInsights} old actioned insights");

        $this->info("Cleanup complete.");

        return self::SUCCESS;
    }
}
