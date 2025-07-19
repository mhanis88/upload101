<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class FileUpload extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'file_uploads';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'original_name',
        'filename',
        'path',
        'size',
        'mime_type',
        'extension',
        'hash',
        'metadata',
        'is_processed',
        'uploaded_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
        'is_processed' => 'boolean',
        'uploaded_at' => 'datetime',
        'size' => 'integer',
    ];

    /**
     * Get the human readable file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if the file is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the file is a document.
     */
    public function isDocument(): bool
    {
        $documentTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        return in_array($this->mime_type, $documentTypes);
    }

    /**
     * Get the file type category.
     */
    public function getFileTypeAttribute(): string
    {
        if ($this->isImage()) {
            return 'image';
        }
        
        if ($this->isDocument()) {
            return 'document';
        }
        
        return 'other';
    }

    /**
     * Get the full storage path.
     */
    public function getFullPathAttribute(): string
    {
        return Storage::disk('uploads')->path($this->path);
    }

    /**
     * Get the public URL for the file.
     */
    public function getUrlAttribute(): string
    {
        // Since uploads disk is private, return download route instead of direct URL
        return route('uploads.download', $this->id);
    }

    /**
     * Check if file exists in storage.
     */
    public function exists(): bool
    {
        return Storage::disk('uploads')->exists($this->path);
    }

    /**
     * Delete the file from storage.
     */
    public function deleteFile(): bool
    {
        if ($this->exists()) {
            return Storage::disk('uploads')->delete($this->path);
        }
        
        return true;
    }

    /**
     * Get file icon based on extension.
     */
    public function getIconAttribute(): string
    {
        $iconMap = [
            'pdf' => 'fa-file-pdf',
            'doc' => 'fa-file-word',
            'docx' => 'fa-file-word',
            'xls' => 'fa-file-excel',
            'xlsx' => 'fa-file-excel',
            'txt' => 'fa-file-text',
            'jpg' => 'fa-file-image',
            'jpeg' => 'fa-file-image',
            'png' => 'fa-file-image',
            'gif' => 'fa-file-image',
            'zip' => 'fa-file-archive',
            'rar' => 'fa-file-archive',
        ];

        return $iconMap[$this->extension] ?? 'fa-file';
    }

    /**
     * Scope for filtering by file type.
     */
    public function scopeByType($query, string $type)
    {
        switch ($type) {
            case 'image':
                return $query->where('mime_type', 'like', 'image/%');
            case 'document':
                return $query->whereIn('mime_type', [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'text/plain',
                ]);
            default:
                return $query;
        }
    }

    /**
     * Scope for searching by filename.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where('original_name', 'like', '%' . $search . '%');
    }

    /**
     * Scope for recent uploads.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('uploaded_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Get upload statistics.
     */
    public static function getStats(): array
    {
        return [
            'total_files' => self::count(),
            'total_size' => self::sum('size'),
            'images_count' => self::where('mime_type', 'like', 'image/%')->count(),
            'documents_count' => self::whereIn('mime_type', [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
            ])->count(),
            'recent_uploads' => self::recent()->count(),
        ];
    }
}
