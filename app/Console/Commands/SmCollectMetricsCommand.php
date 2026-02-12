<?php

namespace App\Console\Commands;

use App\Jobs\SmManager\SmCollectMetricsJob;
use App\Models\Brand;
use Illuminate\Console\Command;

class SmCollectMetricsCommand extends Command
{
    protected $signature = 'sm:collect-metrics';

    protected $description = 'Collect platform metrics for all brands with SM accounts';

    public function handle(): int
    {
        $brands = Brand::whereHas('smAccounts')->get();

        if ($brands->isEmpty()) {
            $this->info('No brands with SM accounts found.');

            return self::SUCCESS;
        }

        $this->info("Dispatching metrics collection for {$brands->count()} brands...");

        foreach ($brands as $brand) {
            $this->line("- Dispatching for: {$brand->name}");
            SmCollectMetricsJob::dispatch($brand);
        }

        $this->info('All metrics collection jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
