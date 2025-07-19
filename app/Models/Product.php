<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'unique_key',
        'product_title',
        'product_description',
        'style_number',
        'sanmar_mainframe_color',
        'size',
        'color_name',
        'piece_price',
        'original_filename',
        'last_imported_at',
        'import_metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'piece_price' => 'decimal:2',
        'last_imported_at' => 'datetime',
        'import_metadata' => 'array',
    ];

    /**
     * CSV field mapping for import
     */
    public static function getCsvFieldMapping(): array
    {
        return [
            'UNIQUE_KEY' => 'unique_key',
            'PRODUCT_TITLE' => 'product_title',
            'PRODUCT_DESCRIPTION' => 'product_description',
            'STYLE#' => 'style_number',
            'SANMAR_MAINFRAME_COLOR' => 'sanmar_mainframe_color',
            'SIZE' => 'size',
            'COLOR_NAME' => 'color_name',
            'PIECE_PRICE' => 'piece_price',
        ];
    }

    /**
     * Clean and validate CSV row data
     */
    public static function cleanCsvRow(array $row): array
    {
        $cleaned = [];
        $mapping = self::getCsvFieldMapping();

        foreach ($mapping as $csvField => $dbField) {
            $value = $row[$csvField] ?? '';
            
            // Clean non-UTF-8 characters
            $value = self::cleanUtf8($value);
            
            // Specific field processing
            switch ($dbField) {
                case 'piece_price':
                    // Clean price field - remove currency symbols, spaces
                    $value = preg_replace('/[^\d.,]/', '', $value);
                    $value = str_replace(',', '', $value);
                    $cleaned[$dbField] = is_numeric($value) ? (float) $value : null;
                    break;
                    
                case 'unique_key':
                    // Ensure unique key is not empty
                    $cleaned[$dbField] = trim($value) ?: null;
                    break;
                    
                default:
                    $cleaned[$dbField] = trim($value) ?: null;
                    break;
            }
        }

        return $cleaned;
    }

    /**
     * Clean non-UTF-8 characters from string
     */
    public static function cleanUtf8(string $text): string
    {
        // First, handle potential encoding issues by converting from various encodings to UTF-8
        $encodings = ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'];
        foreach ($encodings as $encoding) {
            $converted = @mb_convert_encoding($text, 'UTF-8', $encoding);
            if (mb_check_encoding($converted, 'UTF-8')) {
                $text = $converted;
                break;
            }
        }
        
        // Remove invalid UTF-8 sequences
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        
        // Remove or replace problematic characters
        $text = str_replace([
            "\0",           // Null byte
            "\x00",         // Another null byte representation
            "\xFF",         // Invalid UTF-8 byte
            "\xFE",         // Invalid UTF-8 byte
            "\xEF\xBB\xBF", // BOM (Byte Order Mark)
        ], '', $text);
        
        // Remove control characters except tab, newline, and carriage return
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
        
        // Ensure the result is valid UTF-8
        if (!mb_check_encoding($text, 'UTF-8')) {
            // If still invalid, keep only ASCII characters as fallback
            $text = preg_replace('/[^\x20-\x7E\x09\x0A\x0D]/', '', $text);
        }
        
        // Clean up excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Upsert product data using unique_key
     */
    public static function upsertFromCsv(array $data, string $filename): bool
    {
        if (empty($data['unique_key'])) {
            throw new \InvalidArgumentException('unique_key is required for upsert operation');
        }

        // Add import metadata
        $data['original_filename'] = $filename;
        $data['last_imported_at'] = Carbon::now();
        $data['import_metadata'] = [
            'imported_at' => Carbon::now()->toISOString(),
            'filename' => $filename,
            'import_method' => 'csv_upload'
        ];

        // Use updateOrCreate for UPSERT functionality
        $result = self::updateOrCreate(
            ['unique_key' => $data['unique_key']], // WHERE condition
            $data // Data to insert or update
        );

        return $result->wasRecentlyCreated || $result->wasChanged();
    }

    /**
     * Get import statistics
     */
    public static function getImportStats(): array
    {
        return [
            'total_products' => self::count(),
            'recently_imported' => self::where('last_imported_at', '>=', Carbon::now()->subHours(24))->count(),
            'unique_styles' => self::distinct('style_number')->count('style_number'),
            'price_range' => [
                'min' => self::min('piece_price'),
                'max' => self::max('piece_price'),
                'avg' => round(self::avg('piece_price'), 2)
            ],
            'last_import' => self::latest('last_imported_at')->first()?->last_imported_at,
        ];
    }

    /**
     * Search products
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('product_title', 'like', "%{$search}%")
              ->orWhere('product_description', 'like', "%{$search}%")
              ->orWhere('style_number', 'like', "%{$search}%")
              ->orWhere('unique_key', 'like', "%{$search}%")
              ->orWhere('color_name', 'like', "%{$search}%");
        });
    }

    /**
     * Filter by style number
     */
    public function scopeByStyle($query, string $style)
    {
        return $query->where('style_number', $style);
    }

    /**
     * Filter by price range
     */
    public function scopeByPriceRange($query, float $min = null, float $max = null)
    {
        if ($min !== null) {
            $query->where('piece_price', '>=', $min);
        }
        if ($max !== null) {
            $query->where('piece_price', '<=', $max);
        }
        return $query;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return $this->piece_price ? '$' . number_format($this->piece_price, 2) : 'N/A';
    }

    /**
     * Check if product was recently imported
     */
    public function isRecentlyImported(): bool
    {
        return $this->last_imported_at && $this->last_imported_at->isAfter(Carbon::now()->subHours(24));
    }
} 