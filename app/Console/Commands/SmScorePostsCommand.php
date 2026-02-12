<?php

namespace App\Console\Commands;

use App\Jobs\SmManager\SmScorePostsJob;
use App\Models\Brand;
use Illuminate\Console\Command;

class SmScorePostsCommand extends Command
{
    protected $signature = 'sm:score-posts';

    protected $description = 'Score recent SM posts for all brands with SM accounts';

    public function handle(): int
    {
        $brands = Brand::whereHas('smAccounts')->get();

        if ($brands->isEmpty()) {
            $this->info('No brands with SM accounts found.');

            return self::SUCCESS;
        }

        $this->info("Dispatching post scoring for {$brands->count()} brands...");

        foreach ($brands as $brand) {
            $this->line("- Dispatching for: {$brand->name}");
            SmScorePostsJob::dispatch($brand);
        }

        $this->info('All post scoring jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
