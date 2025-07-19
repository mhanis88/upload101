<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('file_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('original_name'); // Original filename from user
            $table->string('filename'); // Stored filename (sanitized/hashed)
            $table->string('path'); // Full storage path
            $table->unsignedBigInteger('size'); // File size in bytes
            $table->string('mime_type'); // MIME type (image/jpeg, application/pdf, etc.)
            $table->string('extension', 10); // File extension
            $table->string('hash', 64)->nullable(); // File hash for duplicate detection
            $table->json('metadata')->nullable(); // Additional metadata (dimensions, etc.)
            $table->boolean('is_processed')->default(false); // Processing status
            $table->timestamp('uploaded_at'); // Upload timestamp
            $table->timestamps();
            
            // Indexes for performance
            $table->index('mime_type');
            $table->index('extension');
            $table->index('uploaded_at');
            $table->index('hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_uploads');
    }
};
