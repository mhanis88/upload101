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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('unique_key')->unique(); // Primary identifier for UPSERT
            $table->string('product_title');
            $table->text('product_description')->nullable();
            $table->string('style_number')->nullable();
            $table->string('sanmar_mainframe_color')->nullable();
            $table->string('size')->nullable();
            $table->string('color_name')->nullable();
            $table->decimal('piece_price', 10, 2)->nullable();
            
            // Additional fields for tracking
            $table->string('original_filename')->nullable();
            $table->timestamp('last_imported_at')->nullable();
            $table->json('import_metadata')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index('unique_key');
            $table->index('product_title');
            $table->index('style_number');
            $table->index('last_imported_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
}; 