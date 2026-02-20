<?php

namespace App\Console\Commands;

use App\Jobs\Apify\CiScrapeCompetitorsJob;
use App\Models\Brand;
use App\Models\CiCompetitor;
use Illuminate\Console\Command;

class CiScrapeCompetitorsCommand extends Command
{
    protected $signature = 'ci:scrape-competitors {--type=profiles : Type of scrape (profiles or posts)}';

    protected $description = 'Scrape competitor profiles or posts for all brands with active competitors';

    public function handle(): int
    {
        $type = $this->option('type');

        if (!in_array($type, ['profiles', 'posts'])) {
            $this->error("Invalid type: {$type}. Use 'profiles' or 'posts'.");
            return self::FAILURE;
        }

        $brandIds = CiCompetitor::where('is_active', true)
            ->distinct('brand_id')
            ->pluck('brand_id');

        $brands = Brand::whereIn('id', $brandIds)->active()->get();

        $this->info("Dispatching {$type} scrape for {$brands->count()} brands...");

        foreach ($brands as $brand) {
            CiScrapeCompetitorsJob::dispatch($brand, $type);
            $this->line("  - {$brand->name}");
        }

        $this->info("Done. Jobs dispatched to queue.");

        return self::SUCCESS;
    }
}
