<?php

namespace App\Console\Commands;

use App\Jobs\Apify\CiScrapeTrendsJob;
use App\Models\Brand;
use App\Models\CiCompetitor;
use Illuminate\Console\Command;

class CiScrapeTrendsCommand extends Command
{
    protected $signature = 'ci:scrape-trends';

    protected $description = 'Scrape trending hashtags and Google Trends for all brands with active competitors';

    public function handle(): int
    {
        $brandIds = CiCompetitor::where('is_active', true)
            ->distinct('brand_id')
            ->pluck('brand_id');

        $brands = Brand::whereIn('id', $brandIds)->active()->get();

        $this->info("Dispatching trends scrape for {$brands->count()} brands...");

        foreach ($brands as $brand) {
            CiScrapeTrendsJob::dispatch($brand);
            $this->line("  - {$brand->name}");
        }

        $this->info("Done. Jobs dispatched to queue.");

        return self::SUCCESS;
    }
}
