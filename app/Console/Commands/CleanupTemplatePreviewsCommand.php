<?php

namespace App\Console\Commands;

use App\Services\TemplateRenderService;
use Illuminate\Console\Command;

class CleanupTemplatePreviewsCommand extends Command
{
    protected $signature = 'previews:cleanup
        {--minutes=60 : Delete files older than this many minutes}
        {--dry-run : Show files that would be deleted without actually deleting}';

    protected $description = 'Clean up old template preview files from temporary storage';

    public function handle(TemplateRenderService $renderService): int
    {
        $minutes = (int) $this->option('minutes');
        $isDryRun = $this->option('dry-run');

        $this->info("Cleaning up preview files older than {$minutes} minutes...");

        if ($isDryRun) {
            $this->warn('Dry run mode - no files will be deleted.');
        }

        try {
            $deleted = $renderService->cleanupOldPreviews($minutes);

            if ($deleted > 0) {
                $action = $isDryRun ? 'would be deleted' : 'deleted';
                $this->info("Successfully {$action}: {$deleted} file(s).");
            } else {
                $this->info('No old preview files found.');
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to cleanup preview files: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
