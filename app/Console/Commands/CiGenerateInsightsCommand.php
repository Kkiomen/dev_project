<?php

namespace App\Console\Commands;

use App\Jobs\Apify\CiGenerateInsightsJob;
use App\Models\Brand;
use App\Models\CiCompetitor;
use Illuminate\Console\Command;

class CiGenerateInsightsCommand extends Command
{
    protected $signature = 'ci:generate-insights';

    protected $description = 'Analyze competitor data and generate insights for all brands';

    public function handle(): int
    {
        $brandIds = CiCompetitor::where('is_active', true)
            ->distinct('brand_id')
            ->pluck('brand_id');

        $brands = Brand::whereIn('id', $brandIds)->active()->get();

        $this->info("Dispatching insight generation for {$brands->count()} brands...");

        foreach ($brands as $brand) {
            CiGenerateInsightsJob::dispatch($brand);
            $this->line("  - {$brand->name}");
        }

        $this->info("Done. Jobs dispatched to queue.");

        return self::SUCCESS;
    }
}
