<?php

namespace App\Console\Commands;

use App\Jobs\SmManager\SmGenerateContentPlanJob;
use App\Models\Brand;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SmGeneratePlansCommand extends Command
{
    protected $signature = 'sm:generate-plans';

    protected $description = 'Generate SM content plans for next month for all brands with active strategies';

    public function handle(): int
    {
        $brands = Brand::whereHas('smAccounts')
            ->whereHas('smStrategies')
            ->get();

        if ($brands->isEmpty()) {
            $this->info('No brands with SM accounts and active strategies found.');

            return self::SUCCESS;
        }

        $nextMonth = Carbon::now()->addMonth()->startOfMonth();

        $this->info("Dispatching content plan generation for {$brands->count()} brands (target: {$nextMonth->format('Y-m')})...");

        foreach ($brands as $brand) {
            $this->line("- Dispatching for: {$brand->name}");
            SmGenerateContentPlanJob::dispatch($brand, $nextMonth->month, $nextMonth->year);
        }

        $this->info('All content plan generation jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
