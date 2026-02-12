<?php

namespace App\Console\Commands;

use App\Jobs\SmManager\SmGenerateWeeklyReportJob;
use App\Models\Brand;
use Illuminate\Console\Command;

class SmWeeklyReportCommand extends Command
{
    protected $signature = 'sm:weekly-report';

    protected $description = 'Generate weekly SM reports for all brands with SM accounts';

    public function handle(): int
    {
        $brands = Brand::whereHas('smAccounts')->get();

        if ($brands->isEmpty()) {
            $this->info('No brands with SM accounts found.');

            return self::SUCCESS;
        }

        $this->info("Dispatching weekly report generation for {$brands->count()} brands...");

        foreach ($brands as $brand) {
            $this->line("- Dispatching for: {$brand->name}");
            SmGenerateWeeklyReportJob::dispatch($brand);
        }

        $this->info('All weekly report jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
