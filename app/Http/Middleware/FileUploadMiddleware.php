<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FileUploadMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to file upload requests
        if ($request->hasFile('files') || $request->is('uploads/*')) {
            
            // Check if user has exceeded upload limits (simple rate limiting)
            $this->checkUploadLimits($request);
            
            // Validate upload environment
            $this->validateUploadEnvironment($request);
            
            // Add security headers
            $this->addSecurityHeaders($request);
        }

        $response = $next($request);

        // Add response headers for file uploads
        if ($request->is('uploads/*')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'DENY');
        }

        return $response;
    }

    /**
     * Check upload limits for rate limiting.
     */
    private function checkUploadLimits(Request $request): void
    {
        $ip = $request->ip();
        $sessionKey = "upload_count_{$ip}";
        $timeKey = "upload_time_{$ip}";
        
        $currentTime = time();
        $lastUploadTime = session($timeKey, 0);
        $uploadCount = session($sessionKey, 0);
        
        // Reset counter if more than 1 hour has passed
        if ($currentTime - $lastUploadTime > 3600) {
            $uploadCount = 0;
        }
        
        // Limit: 50 uploads per hour per IP
        if ($uploadCount >= 50) {
            abort(429, 'Too many upload attempts. Please try again later.');
        }
        
        // Update counters
        session([$sessionKey => $uploadCount + 1]);
        session([$timeKey => $currentTime]);
    }

    /**
     * Validate upload environment.
     */
    private function validateUploadEnvironment(Request $request): void
    {
        // Check if uploads directory exists and is writable
        $uploadPath = storage_path('app/uploads');
        
        if (!is_dir($uploadPath)) {
            if (!mkdir($uploadPath, 0755, true)) {
                abort(500, 'Upload directory could not be created.');
            }
        }
        
        if (!is_writable($uploadPath)) {
            abort(500, 'Upload directory is not writable.');
        }
        
        // Check available disk space (require at least 100MB free)
        $freeSpace = disk_free_space($uploadPath);
        if ($freeSpace !== false && $freeSpace < 100 * 1024 * 1024) {
            abort(507, 'Insufficient storage space for upload.');
        }
    }

    /**
     * Add security headers to request.
     */
    private function addSecurityHeaders(Request $request): void
    {
        // Add CSRF token to request if not present (for AJAX uploads)
        if (!$request->hasHeader('X-CSRF-TOKEN') && !$request->has('_token')) {
            $request->headers->set('X-CSRF-TOKEN', csrf_token());
        }
    }
} 