<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Services\Automation\PillarDistributionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class InitializeWeekTrackingCommand extends Command
{
    protected $signature = 'automation:init-week {--brand= : Initialize specific brand by public_id}';

    protected $description = 'Initialize pillar tracking for the current week';

    public function __construct(
        protected PillarDistributionService $pillarService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $brandPublicId = $this->option('brand');
        $weekStart = Carbon::now()->startOfWeek();

        if ($brandPublicId) {
            $brand = Brand::where('public_id', $brandPublicId)->first();

            if (!$brand) {
                $this->error("Brand not found: {$brandPublicId}");
                return self::FAILURE;
            }

            $this->initializeForBrand($brand, $weekStart);
            return self::SUCCESS;
        }

        // Initialize for all brands with automation enabled
        $brands = Brand::where('automation_enabled', true)
            ->where('is_active', true)
            ->where('onboarding_completed', true)
            ->get();

        if ($brands->isEmpty()) {
            $this->info('No brands with automation enabled found.');
            return self::SUCCESS;
        }

        $this->info("Initializing week tracking for {$brands->count()} brands...");

        foreach ($brands as $brand) {
            $this->initializeForBrand($brand, $weekStart);
        }

        $this->info('Week tracking initialized successfully.');

        return self::SUCCESS;
    }

    protected function initializeForBrand(Brand $brand, Carbon $weekStart): void
    {
        $this->line("- Initializing tracking for: {$brand->name}");

        $pillars = $brand->getContentPillars();

        if (empty($pillars)) {
            $this->warn("  No content pillars defined, skipping.");
            return;
        }

        $this->pillarService->initializeWeekTracking($brand, $weekStart);

        $this->info("  Initialized {$this->countPillars($pillars)} pillars for week {$weekStart->weekOfYear}");
    }

    protected function countPillars(array $pillars): int
    {
        return count($pillars);
    }
}
