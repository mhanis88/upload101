<?php

namespace App\Jobs;

use App\Models\FileUpload;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProcessCsvImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $fileUpload;
    protected $results = [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'errors' => [],
        'skipped' => 0
    ];

    /**
     * Create a new job instance.
     */
    public function __construct(FileUpload $fileUpload)
    {
        $this->fileUpload = $fileUpload;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting CSV processing for file: {$this->fileUpload->original_name}");
            
            // Mark file as processing
            $this->fileUpload->update([
                'is_processed' => false,
                'metadata' => array_merge($this->fileUpload->metadata ?? [], [
                    'processing_started_at' => Carbon::now()->toISOString(),
                    'status' => 'processing'
                ])
            ]);

            // Process the CSV file
            $this->processCsvFile();

            // Mark as completed
            $this->fileUpload->update([
                'is_processed' => true,
                'metadata' => array_merge($this->fileUpload->metadata ?? [], [
                    'processing_completed_at' => Carbon::now()->toISOString(),
                    'status' => 'completed',
                    'results' => $this->results
                ])
            ]);

            Log::info("CSV processing completed for file: {$this->fileUpload->original_name}", $this->results);

        } catch (\Exception $e) {
            Log::error("CSV processing failed for file: {$this->fileUpload->original_name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Mark as failed
            $this->fileUpload->update([
                'is_processed' => false,
                'metadata' => array_merge($this->fileUpload->metadata ?? [], [
                    'processing_failed_at' => Carbon::now()->toISOString(),
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'results' => $this->results
                ])
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * Process the CSV file
     */
    private function processCsvFile(): void
    {
        if (!$this->fileUpload->exists()) {
            throw new \Exception('File not found in storage');
        }

        $filePath = $this->fileUpload->full_path;
        
        // Validate file is CSV
        if (!$this->isCsvFile($filePath)) {
            throw new \Exception('File is not a valid CSV file');
        }

        // Open and process CSV
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \Exception('Unable to open CSV file');
        }
        
        $headers = [];
        $rowNumber = 0;

        while (($line = fgets($handle)) !== false) {
            $rowNumber++;

            // Remove BOM if present and convert to UTF-8, ignoring invalid characters
            $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
            // Remove any non-UTF-8 characters
            $line = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]/u', '', $line);

            $row = str_getcsv($line);

            // Skip empty lines
            if ($row === false || (count($row) === 1 && trim($row[0]) === '')) {
                continue;
            }

            // First row should be headers
            if ($rowNumber === 1) {
                $headers = $this->cleanHeaders($row);
                $this->validateHeaders($headers);
                continue;
            }

            try {
                $this->processRow($headers, $row, $rowNumber);
            } catch (\Exception $e) {
                $this->results['errors'][] = [
                    'row' => $rowNumber,
                    'error' => $e->getMessage(),
                    'data' => $row
                ];
                Log::warning("Error processing row {$rowNumber}: " . $e->getMessage());
            }
        }

        fclose($handle);

        if (empty($this->results['processed'])) {
            throw new \Exception('No valid rows were processed from the CSV file');
        }
    }

    /**
     * Check if file is CSV
     */
    private function isCsvFile(string $filePath): bool
    {
        $mimeType = mime_content_type($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        return in_array($mimeType, ['text/csv', 'text/plain', 'application/csv']) || 
               strtolower($extension) === 'csv';
    }

    /**
     * Clean and validate CSV headers
     */
    private function cleanHeaders(array $headers): array
    {
        $cleaned = [];
        foreach ($headers as $header) {
            $cleaned[] = Product::cleanUtf8(trim($header));
        }
        return $cleaned;
    }

    /**
     * Validate required headers are present
     */
    private function validateHeaders(array $headers): void
    {
        $required = ['UNIQUE_KEY', 'PRODUCT_TITLE'];
        $missing = [];

        $headers = array_map('trim', $headers);

        foreach ($required as $requiredHeader) {
            if (!in_array($requiredHeader, $headers)) {
                $missing[] = $requiredHeader;
            }
        }

        if (!empty($missing)) {
            throw new \Exception('Missing required CSV headers: ' . implode(', ', $missing));
        }
    }

    /**
     * Process a single CSV row
     */
    private function processRow(array $headers, array $row, int $rowNumber): void
    {
        // Combine headers with row data
        $data = array_combine($headers, $row);
        if ($data === false) {
            throw new \Exception('Row data does not match headers');
        }

        // Clean the row data
        $cleanedData = Product::cleanCsvRow($data);

        // Validate required fields
        if (empty($cleanedData['unique_key'])) {
            throw new \Exception('UNIQUE_KEY is required but empty');
        }

        if (empty($cleanedData['product_title'])) {
            throw new \Exception('PRODUCT_TITLE is required but empty');
        }

        // Check if this is an update or create
        $existingProduct = Product::where('unique_key', $cleanedData['unique_key'])->first();
        $isUpdate = $existingProduct !== null;

        // Perform UPSERT
        $wasChanged = Product::upsertFromCsv($cleanedData, $this->fileUpload->original_name);

        // Update counters
        $this->results['processed']++;
        if ($isUpdate) {
            if ($wasChanged) {
                $this->results['updated']++;
            } else {
                $this->results['skipped']++; // No changes needed
            }
        } else {
            $this->results['created']++;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("CSV import job failed for file: {$this->fileUpload->original_name}", [
            'error' => $exception->getMessage(),
            'results' => $this->results
        ]);

        // Update file upload record
        $this->fileUpload->update([
            'is_processed' => false,
            'metadata' => array_merge($this->fileUpload->metadata ?? [], [
                'processing_failed_at' => Carbon::now()->toISOString(),
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'results' => $this->results
            ])
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'csv-import',
            'file:' . $this->fileUpload->id,
            'filename:' . $this->fileUpload->original_name
        ];
    }
} 