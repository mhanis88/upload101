<?php

namespace App\Http\Controllers;

use App\Models\FileUpload;
use App\Models\Product;
use App\Http\Requests\FileUploadRequest;
use App\Jobs\ProcessCsvImport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class FileUploadController extends Controller
{
    /**
     * Display a listing of uploaded CSV files and products.
     */
    public function index(Request $request)
    {
        // Get recent CSV uploads
        $files = FileUpload::where('mime_type', 'text/csv')
                          ->orWhere('extension', 'csv')
                          ->orderBy('uploaded_at', 'desc')
                          ->limit(50)
                          ->get();

        // Get product statistics
        $stats = Product::getImportStats();

        // Get recent products for display
        $products = Product::with([])
                          ->when($request->filled('search'), function ($query) use ($request) {
                              $query->search($request->search);
                          })
                          ->when($request->filled('style'), function ($query) use ($request) {
                              $query->byStyle($request->style);
                          })
                          ->orderBy('last_imported_at', 'desc')
                          ->limit(100)
                          ->get();

        return view('uploads.index', compact('files', 'stats', 'products'));
    }

    /**
     * Store uploaded CSV files and dispatch processing job.
     */
    public function store(FileUploadRequest $request)
    {
        $uploadedFiles = [];
        $errors = [];

        // Process each uploaded file
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                try {
                    // Validate it's a CSV file
                    if (!$this->isCsvFile($file)) {
                        throw new \Exception('Only CSV files are allowed for product import');
                    }

                    $uploadedFile = $this->processFile($file);
                    $uploadedFiles[] = $uploadedFile;

                    // Dispatch background job for CSV processing
                    ProcessCsvImport::dispatch($uploadedFile);

                } catch (\Exception $e) {
                    $errors[] = "Error uploading {$file->getClientOriginalName()}: " . $e->getMessage();
                }
            }
        }

        // Return response based on results
        if (empty($uploadedFiles) && !empty($errors)) {
            return back()->withErrors($errors)->withInput();
        }

        $message = count($uploadedFiles) . ' CSV file(s) uploaded and queued for processing.';
        if (!empty($errors)) {
            $message .= ' Some files failed to upload.';
        }

        return redirect()->route('uploads.index')
                        ->with('success', $message)
                        ->with('uploaded_files', $uploadedFiles)
                        ->with('upload_errors', $errors);
    }

    /**
     * Download the specified file.
     */
    public function download(FileUpload $fileUpload)
    {
        if (!$fileUpload->exists()) {
            abort(404, 'File not found in storage.');
        }

        return response()->download(
            $fileUpload->full_path, 
            $fileUpload->original_name
        );
    }

    /**
     * Remove the specified file.
     */
    public function destroy(FileUpload $fileUpload)
    {
        try {
            // Delete file from storage
            $fileUpload->deleteFile();
            
            // Delete database record
            $fileUpload->delete();

            return redirect()->route('uploads.index')
                           ->with('success', 'File deleted successfully.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete file: ' . $e->getMessage()]);
        }
    }

    /**
     * Bulk delete files.
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:file_uploads,id'
        ]);

        $deleted = 0;
        $errors = [];

        foreach ($request->file_ids as $fileId) {
            try {
                $file = FileUpload::findOrFail($fileId);
                $file->deleteFile();
                $file->delete();
                $deleted++;
            } catch (\Exception $e) {
                $errors[] = "Failed to delete file ID {$fileId}: " . $e->getMessage();
            }
        }

        $message = "{$deleted} file(s) deleted successfully.";
        if (!empty($errors)) {
            $message .= " Some files failed to delete.";
        }

        return redirect()->route('uploads.index')
                        ->with('success', $message)
                        ->with('bulk_errors', $errors);
    }

    /**
     * Check if uploaded file is CSV
     */
    private function isCsvFile($file): bool
    {
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        
        return in_array($mimeType, ['text/csv', 'text/plain', 'application/csv']) || 
               $extension === 'csv';
    }

    /**
     * Process and store a single file.
     */
    private function processFile($file)
    {
        // Generate secure filename with date-based organization
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = Str::uuid() . '.' . $extension;

        // Organize files by year/month for better file system performance
        $dateFolder = Carbon::now()->format('Y/m');

        // Clean up any non-UTF-8 characters before storing the file
        $tmpPath = $file->getRealPath();
        $cleanedContent = '';
        $handle = fopen($tmpPath, 'r');
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                // Remove BOM if present and convert to UTF-8, ignoring invalid characters
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
                $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                // Remove any non-UTF-8 characters
                $line = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]/u', '', $line);
                $cleanedContent .= $line;
            }
            fclose($handle);
        } else {
            // fallback: just get the raw contents
            $cleanedContent = file_get_contents($tmpPath);
        }

        // Store cleaned content in uploads disk
        $path = $dateFolder . '/' . $filename;
        Storage::disk('uploads')->put($path, $cleanedContent);

        // Calculate file hash for duplicate detection (hash the cleaned content)
        $hash = hash('sha256', $cleanedContent);

        // Check for duplicate CSV files by hash
        $existingFile = FileUpload::where('hash', $hash)->first();
        if ($existingFile) {
            // Delete the newly uploaded file since it's a duplicate
            Storage::disk('uploads')->delete($path);

            // Re-process the existing file instead of creating duplicate
            ProcessCsvImport::dispatch($existingFile);

            return $existingFile; // Return existing file for idempotent behavior
        }

        // Get file metadata
        $metadata = $this->getFileMetadata($file);

        // Create database record
        $fileUpload = FileUpload::create([
            'original_name' => $originalName,
            'filename' => $filename,
            'path' => $path,
            'size' => strlen($cleanedContent),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'hash' => $hash,
            'metadata' => $metadata,
            'is_processed' => false, // Will be processed by background job
            'uploaded_at' => Carbon::now(),
        ]);

        return $fileUpload;
    }

    /**
     * Extract metadata from file.
     */
    private function getFileMetadata($file)
    {
        $metadata = [
            'original_size' => $file->getSize(),
            'upload_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'file_type' => 'csv_import',
            'status' => 'queued',
            'queued_at' => Carbon::now()->toISOString(),
        ];

        return $metadata;
    }

    /**
     * Get import statistics API endpoint.
     */
    public function stats()
    {
        return response()->json([
            'files' => FileUpload::where('extension', 'csv')->count(),
            'products' => Product::getImportStats()
        ]);
    }

    /**
     * Search products API endpoint.
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:255'
        ]);

        $products = Product::search($request->query)
                          ->orderBy('last_imported_at', 'desc')
                          ->limit(10)
                          ->get();

        return response()->json($products);
    }

    /**
     * Get processing status for a file
     */
    public function status(FileUpload $fileUpload)
    {
        return response()->json([
            'id' => $fileUpload->id,
            'filename' => $fileUpload->original_name,
            'is_processed' => $fileUpload->is_processed,
            'status' => $fileUpload->metadata['status'] ?? 'unknown',
            'results' => $fileUpload->metadata['results'] ?? null,
            'uploaded_at' => $fileUpload->uploaded_at,
            'processing_started_at' => $fileUpload->metadata['processing_started_at'] ?? null,
            'processing_completed_at' => $fileUpload->metadata['processing_completed_at'] ?? null,
        ]);
    }

    /**
     * Reprocess a CSV file
     */
    public function reprocess(FileUpload $fileUpload)
    {
        if (!$this->isCsvFile($fileUpload)) {
            return back()->withErrors(['error' => 'Only CSV files can be reprocessed']);
        }

        // Dispatch the processing job again
        ProcessCsvImport::dispatch($fileUpload);

        return back()->with('success', 'CSV file queued for reprocessing');
    }
} 