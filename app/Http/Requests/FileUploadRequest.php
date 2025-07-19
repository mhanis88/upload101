<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class FileUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'files' => [
                'required',
                'array',
                'max:5', // Reduced for CSV processing
            ],
            'files.*' => [
                'required',
                'file',
                'max:51200', // 50MB for large CSV files
                'mimes:csv,txt',
                'mimetypes:text/csv,text/plain,application/csv',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'files.required' => 'Please select at least one CSV file to upload.',
            'files.array' => 'Files must be provided as an array.',
            'files.max' => 'You can upload a maximum of 5 CSV files at once.',
            
            'files.*.required' => 'Each file is required.',
            'files.*.file' => 'Each upload must be a valid file.',
            'files.*.max' => 'Each CSV file must not exceed 50MB in size.',
            'files.*.mimes' => 'Only CSV files are allowed for product import.',
            'files.*.mimetypes' => 'Only CSV files (text/csv, text/plain) are allowed.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'files' => 'CSV files',
            'files.*' => 'CSV file',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Additional custom validation for CSV files
            if ($this->hasFile('files')) {
                $totalSize = 0;
                foreach ($this->file('files') as $index => $file) {
                    $totalSize += $file->getSize();
                    
                    // Validate CSV file structure
                    $this->validateCsvFile($file, $validator, $index);
                }

                // Check total upload size (max 250MB for all CSV files combined)
                if ($totalSize > 250 * 1024 * 1024) {
                    $validator->errors()->add('files', 'The total size of all CSV files must not exceed 250MB.');
                }
            }
        });
    }

    /**
     * Validate CSV file structure and content.
     */
    private function validateCsvFile($file, $validator, $index)
    {
        try {
            // Check if file is readable
            $handle = fopen($file->getPathname(), 'r');
            if (!$handle) {
                $validator->errors()->add("files.{$index}", 'CSV file is not readable.');
                return;
            }

            // Read first few lines to validate structure
            // Read the first line and clean up any non-UTF-8 characters
            $line = fgets($handle);
            if ($line !== false) {
                // Remove BOM if present and convert to UTF-8, ignoring invalid characters
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
                $line = mb_convert_encoding($line, 'UTF-8', 'UTF-8');
                // Remove any non-UTF-8 characters
                $line = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]/u', '', $line);
                $headerRow = str_getcsv($line);
            } else {
                $headerRow = false;
            }
            
            if ($headerRow === false) {
                $validator->errors()->add("files.{$index}", 'CSV file appears to be empty or corrupted.');
                fclose($handle);
                return;
            }

            // Check for required headers
            $requiredHeaders = ['UNIQUE_KEY', 'PRODUCT_TITLE'];
            $headers = array_map('trim', $headerRow);
            
            foreach ($requiredHeaders as $required) {
                if (!in_array($required, $headers)) {
                    $validator->errors()->add("files.{$index}", "CSV file is missing required header: {$required}");
                }
            }

            // Check if there's at least one data row
            // Read the next line and clean up any non-UTF-8 characters before parsing as CSV
            $dataLine = fgets($handle);
            if ($dataLine !== false) {
                // Remove BOM if present and convert to UTF-8, ignoring invalid characters
                $dataLine = preg_replace('/^\xEF\xBB\xBF/', '', $dataLine);
                $dataLine = mb_convert_encoding($dataLine, 'UTF-8', 'UTF-8');
                // Remove any non-UTF-8 characters
                $dataLine = preg_replace('/[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]/u', '', $dataLine);
                $dataRow = str_getcsv($dataLine);
            } else {
                $dataRow = false;
            }
            if ($dataRow === false) {
                $validator->errors()->add("files.{$index}", 'CSV file must contain at least one data row.');
            }

            fclose($handle);

        } catch (\Exception $e) {
            $validator->errors()->add("files.{$index}", 'Error validating CSV file: ' . $e->getMessage());
        }
    }
} 