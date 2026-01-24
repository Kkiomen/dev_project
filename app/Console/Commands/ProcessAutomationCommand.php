<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAutomationJob;
use App\Models\Brand;
use Illuminate\Console\Command;

class ProcessAutomationCommand extends Command
{
    protected $signature = 'automation:process {--brand= : Process specific brand by public_id}';

    protected $description = 'Process content automation for brands';

    public function handle(): int
    {
        $brandPublicId = $this->option('brand');

        if ($brandPublicId) {
            // Process specific brand
            $brand = Brand::where('public_id', $brandPublicId)->first();

            if (!$brand) {
                $this->error("Brand not found: {$brandPublicId}");
                return self::FAILURE;
            }

            $this->info("Processing automation for brand: {$brand->name}");
            ProcessAutomationJob::dispatch($brand);
            $this->info('Job dispatched successfully.');

            return self::SUCCESS;
        }

        // Process all brands with automation enabled
        $brands = Brand::where('automation_enabled', true)
            ->where('is_active', true)
            ->where('onboarding_completed', true)
            ->get();

        if ($brands->isEmpty()) {
            $this->info('No brands with automation enabled found.');
            return self::SUCCESS;
        }

        $this->info("Processing automation for {$brands->count()} brands...");

        foreach ($brands as $brand) {
            $this->line("- Dispatching job for: {$brand->name}");
            ProcessAutomationJob::dispatch($brand);
        }

        $this->info('All jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
