<?php

namespace App\Console\Commands;

use App\Services\SmManager\SmPublishOrchestratorService;
use Illuminate\Console\Command;

class SmPublishDueCommand extends Command
{
    protected $signature = 'sm:publish-due';

    protected $description = 'Publish all SM scheduled posts that are due';

    public function handle(SmPublishOrchestratorService $service): int
    {
        $count = $service->publishDuePosts();
        $this->info("Published {$count} scheduled posts.");

        return self::SUCCESS;
    }
}
