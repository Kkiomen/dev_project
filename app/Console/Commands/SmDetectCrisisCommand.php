<?php

namespace App\Console\Commands;

use App\Jobs\SmManager\SmDetectCrisisJob;
use App\Models\Brand;
use Illuminate\Console\Command;

class SmDetectCrisisCommand extends Command
{
    protected $signature = 'sm:detect-crisis';

    protected $description = 'Run crisis detection for all brands with SM accounts';

    public function handle(): int
    {
        $brands = Brand::whereHas('smAccounts')->get();

        if ($brands->isEmpty()) {
            $this->info('No brands with SM accounts found.');

            return self::SUCCESS;
        }

        $this->info("Dispatching crisis detection for {$brands->count()} brands...");

        foreach ($brands as $brand) {
            $this->line("- Dispatching for: {$brand->name}");
            SmDetectCrisisJob::dispatch($brand);
        }

        $this->info('All crisis detection jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
