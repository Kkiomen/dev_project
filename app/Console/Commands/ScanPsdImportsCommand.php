<?php

namespace App\Console\Commands;

use App\Jobs\ImportPsdFileJob;
use App\Models\PsdImport;
use Database\Seeders\SystemUserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScanPsdImportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'psd:scan {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan PSD directory for new files to import';

    /**
     * Directory to scan for PSD files.
     */
    protected const SCAN_DIRECTORY = 'psd';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info(__('bulk_import.messages.scanning'));

        $directory = self::SCAN_DIRECTORY;

        if (!Storage::disk('local')->exists($directory)) {
            Storage::disk('local')->makeDirectory($directory);
            $this->info(__('bulk_import.messages.directory_created', ['directory' => $directory]));
            return Command::SUCCESS;
        }

        $files = Storage::disk('local')->files($directory);
        $psdFiles = array_filter($files, fn($file) => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'psd');

        if (empty($psdFiles)) {
            $this->info(__('bulk_import.messages.no_files_found'));
            return Command::SUCCESS;
        }

        $this->info(__('bulk_import.messages.files_found', ['count' => count($psdFiles)]));

        $newCount = 0;
        $skippedCount = 0;

        foreach ($psdFiles as $filePath) {
            $filename = basename($filePath);
            $fullPath = Storage::disk('local')->path($filePath);

            // Calculate file hash
            $fileHash = hash_file('sha256', $fullPath);

            // Check if already imported
            if (PsdImport::where('file_hash', $fileHash)->exists()) {
                $this->line("  <comment>⏭</comment>  {$filename} - " . __('bulk_import.messages.already_imported'));
                $skippedCount++;
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  <info>📋</info>  {$filename} - " . __('bulk_import.messages.would_import'));
                $newCount++;
                continue;
            }

            // Create PsdImport record
            $import = PsdImport::create([
                'filename' => $filename,
                'file_hash' => $fileHash,
                'file_path' => $filePath,
                'file_size' => Storage::disk('local')->size($filePath),
                'status' => PsdImport::STATUS_PENDING,
                'metadata' => [
                    'scanned_at' => now()->toISOString(),
                    'original_path' => $filePath,
                ],
            ]);

            // Get system user
            $systemUser = SystemUserSeeder::getSystemUser();

            // Dispatch import job
            ImportPsdFileJob::dispatch($import, $systemUser);

            $this->line("  <info>✓</info>  {$filename} - " . __('bulk_import.messages.queued'));
            $newCount++;

            Log::info('PSD Scan: New file queued for import', [
                'import_id' => $import->id,
                'filename' => $filename,
                'file_hash' => $fileHash,
            ]);
        }

        $this->newLine();
        $this->info(__('bulk_import.messages.scan_complete', [
            'new' => $newCount,
            'skipped' => $skippedCount,
        ]));

        return Command::SUCCESS;
    }
}
