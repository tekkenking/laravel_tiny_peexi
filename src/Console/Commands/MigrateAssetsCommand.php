<?php

namespace Tekkenking\TinyPeexi\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Tekkenking\TinyPeexi\Jobs\GenerateTinyPeexiVariantJob;
use Tekkenking\TinyPeexi\Services\TinyPeexiClient;

class MigrateAssetsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tinypeexi:migrate 
                            {--path= : Pipe-separated paths} 
                            {--ext= : Pipe-separated extensions (e.g. jpg|png)} 
                            {--starts-with= : Pipe-separated prefixes} 
                            {--variants= : Pipe-separated variant dimensions (e.g. w800,h900|w200)}
                            {--batch=50 : Number of files to upload per request}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing local assets to TinyPeexi (with idempotency via CSV and queued variant jobs)';

    protected string $csvPath = 'tinypeexi_migration_results.csv';

    /**
     * Execute the console command.
     */
    public function handle(TinyPeexiClient $client)
    {
        // 1. Parsing Inputs
        $paths = $this->parsePipeOption($this->option('path'));
        $exts = $this->parsePipeOption($this->option('ext'));
        $startsWith = $this->parsePipeOption($this->option('starts-with'));
        $variantsOpt = $this->option('variants');
        $batchSize = (int) $this->option('batch');

        if (empty($paths)) {
            $this->error('Please provide at least one --path');
            return 1;
        }

        $variantConfigs = $this->parseVariants($variantsOpt);

        // 2. Idempotency & State Tracking
        $this->info("Initializing state tracker...");
        $processedFiles = $this->loadProcessedFiles();
        $this->info("Found " . count($processedFiles) . " previously processed files.");

        $csvAbsolutePath = storage_path('app/' . $this->csvPath);

        // Ensure storage directory exists
        if (!is_dir(dirname($csvAbsolutePath))) {
            mkdir(dirname($csvAbsolutePath), 0755, true);
        }

        $csvHandle = fopen($csvAbsolutePath, 'a');
        if ($csvHandle === false) {
            $this->error("Failed to open CSV for writing: {$csvAbsolutePath}");
            return 1;
        }

        // Write CSV header if empty
        if (count($processedFiles) === 0 && filesize($csvAbsolutePath) === 0) {
            fputcsv($csvHandle, ['Original name', 'Sha name', 'Time of success']);
        }

        // 3. Scanning & Filtering
        $this->info("Scanning directories...");
        $finder = new Finder();
        $finder->files()->in($paths);

        if (!empty($exts)) {
            foreach ($exts as $ext) {
                // Ignore the dot if users provided .jpg
                $finder->name('*.' . ltrim($ext, '.'));
            }
        }

        $batch = [];
        $totalUploaded = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();
            $filename = $file->getFilename();

            // Filter by prefix
            if (!empty($startsWith)) {
                $matchesPrefix = false;
                foreach ($startsWith as $prefix) {
                    if (str_starts_with($filename, $prefix)) {
                        $matchesPrefix = true;
                        break;
                    }
                }
                if (!$matchesPrefix) {
                    continue; // Skip silently
                }
            }

            // 4. Skipping Completed Files
            if (isset($processedFiles[$realPath])) {
                $totalSkipped++;
                continue;
            }

            $batch[] = $realPath;

            // 5. Batch Uploading
            if (count($batch) >= $batchSize) {
                $this->processBatch($client, $batch, $variantConfigs, $csvHandle, $totalUploaded, $totalFailed);
                $batch = [];
            }
        }

        // Process any remaining files
        if (count($batch) > 0) {
            $this->processBatch($client, $batch, $variantConfigs, $csvHandle, $totalUploaded, $totalFailed);
        }

        fclose($csvHandle);

        $this->newLine();
        $this->info("Migration completed.");
        $this->table(
            ['Uploaded', 'Skipped', 'Failed'],
            [[$totalUploaded, $totalSkipped, $totalFailed]]
        );

        return 0;
    }

    protected function processBatch(
        TinyPeexiClient $client,
        array $batchPaths,
        array $variantConfigs,
        $csvHandle,
        int &$totalUploaded,
        int &$totalFailed
    ) {
        $this->info("Uploading batch of " . count($batchPaths) . " files...");

        try {
            $uploadedAssets = $client->uploadMany($batchPaths);

            if (count($uploadedAssets) !== count($batchPaths)) {
                $this->warn("Warning: Received " . count($uploadedAssets) . " assets but uploaded " . count($batchPaths) . ". Skipping tracking for this batch due to mismatch.");
                $totalFailed += count($batchPaths);
                return;
            }

            // 6. CSV Tracking (Appending) and 7. Queued Variants
            foreach ($uploadedAssets as $index => $assetDto) {
                $originalPath = $batchPaths[$index] ?? null;
                $sha = $assetDto->sha;

                fputcsv($csvHandle, [
                    $originalPath,
                    $sha,
                    now()->toDateTimeString()
                ]);
                $totalUploaded++;

                // Dispatch background jobs for variants
                foreach ($variantConfigs as $params) {
                    GenerateTinyPeexiVariantJob::dispatch($sha, $params);
                }
            }
        } catch (\Exception $e) {
            $this->error("Upload batch failed: " . $e->getMessage());
            $totalFailed += count($batchPaths);
        }
    }

    protected function parsePipeOption(?string $option): array
    {
        if (empty($option)) {
            return [];
        }
        $parts = explode('|', $option);
        return array_values(array_filter(array_map('trim', $parts)));
    }

    protected function parseVariants(?string $variantsOpt): array
    {
        $configs = [];
        if (empty($variantsOpt)) {
            return $configs;
        }

        $parts = explode('|', $variantsOpt);
        foreach ($parts as $part) {
            $config = [];
            $dims = explode(',', $part);
            foreach ($dims as $dim) {
                $dim = trim($dim);
                if (str_starts_with($dim, 'w')) {
                    $config['w'] = (int) substr($dim, 1);
                } elseif (str_starts_with($dim, 'h')) {
                    $config['h'] = (int) substr($dim, 1);
                }
            }
            if (!empty($config)) {
                $configs[] = $config;
            }
        }
        return $configs;
    }

    protected function loadProcessedFiles(): array
    {
        $processed = [];
        $csvAbsolutePath = storage_path('app/' . $this->csvPath);

        if (file_exists($csvAbsolutePath)) {
            $handle = fopen($csvAbsolutePath, 'r');
            if ($handle !== false) {
                // Skip header
                $header = fgetcsv($handle);
                while (($data = fgetcsv($handle)) !== false) {
                    if (isset($data[0])) {
                        $processed[$data[0]] = true; // Memory efficient boolean storage
                    }
                }
                fclose($handle);
            }
        }

        return $processed;
    }
}
