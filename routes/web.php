<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileUploadController;

// Home route - redirect to uploads
Route::get('/', function () {
    return redirect()->route('uploads.index');
});

// File Upload Routes
Route::prefix('uploads')->name('uploads.')->middleware(['web', 'file.upload'])->group(function () {
    // Main unified interface
    Route::get('/', [FileUploadController::class, 'index'])->name('index');
    Route::post('/', [FileUploadController::class, 'store'])->name('store');
    Route::get('/{fileUpload}/download', [FileUploadController::class, 'download'])->name('download');
    Route::delete('/{fileUpload}', [FileUploadController::class, 'destroy'])->name('destroy');
    
    // CSV Processing specific routes
    Route::get('/{fileUpload}/status', [FileUploadController::class, 'status'])->name('status');
    Route::post('/{fileUpload}/reprocess', [FileUploadController::class, 'reprocess'])->name('reprocess');
    
    // Bulk operations
    Route::post('/bulk-delete', [FileUploadController::class, 'bulkDelete'])->name('bulk-delete');
    
    // API routes for AJAX
    Route::get('/api/stats', [FileUploadController::class, 'stats'])->name('api.stats');
    Route::get('/api/search', [FileUploadController::class, 'search'])->name('api.search');
});

// Legacy redirects
Route::redirect('/files', '/uploads');
Route::redirect('/uploads/create', '/uploads');
