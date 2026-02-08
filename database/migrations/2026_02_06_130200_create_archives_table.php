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
        Schema::create('archives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('document_number')->unique()->nullable();
            $table->text('description')->nullable();
            $table->uuid('category_id')->nullable();

            // File storage (MinIO/S3 path - PRIVATE)
            $table->text('file_path'); // Full MinIO path (can exceed 255 chars with nested directories)
            $table->string('file_name', 500); // Generated safe filename
            $table->string('original_filename', 500); // User's original filename (may be very long)
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size'); // bytes
            $table->string('file_hash', 64)->nullable(); // SHA-256 hash for integrity check

            // Metadata JSON
            $table->json('metadata')->nullable(); // {"author": "...", "tags": [], "custom_fields": {}}

            // Audit & Security
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->ipAddress('uploaded_ip')->nullable();
            $table->text('uploaded_user_agent')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->unsignedInteger('access_count')->default(0);

            // Archival data
            $table->date('archive_date')->nullable();
            $table->string('retention_period', 50)->nullable(); // e.g., "5 years", "permanent"
            $table->timestamp('retention_until')->nullable();

            // Status
            $table->enum('status', ['draft', 'published', 'archived', 'deleted'])->default('draft');
            $table->boolean('is_confidential')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes untuk performance
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index(['status', 'is_confidential', 'deleted_at']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index(['file_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('archives');
    }
};
