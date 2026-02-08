<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Laravel\Scout\Searchable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Archive extends Model
{
    use HasFactory, HasUuids, SoftDeletes, Searchable, LogsActivity;

    protected $fillable = [
        'title',
        'document_number',
        'description',
        'category_id',
        'file_path',
        'file_name',
        'original_filename',
        'mime_type',
        'file_size',
        'file_hash',
        'metadata',
        'uploaded_by',
        'uploaded_ip',
        'uploaded_user_agent',
        'last_accessed_at',
        'access_count',
        'archive_date',
        'retention_period',
        'retention_until',
        'status',
        'is_confidential',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_accessed_at' => 'datetime',
        'archive_date' => 'date',
        'retention_until' => 'datetime',
        'is_confidential' => 'boolean',
    ];

    // Scout Searchable Configuration
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'document_number' => $this->document_number,
            'description' => $this->description,
            'original_filename' => $this->original_filename,
            'category_name' => $this->category?->name,
            'status' => $this->status,
        ];
    }

    // Activity Log Configuration
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'document_number', 'description', 'category_id', 'status', 'is_confidential'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Helper Methods
    public function getFileUrl(): string
    {
        // Generate temporary signed URL (5 menit) untuk download private file
        // Note: MinIO S3 driver supports temporaryUrl via AWS SDK
        try {
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('minio');
            return $disk->temporaryUrl(
                $this->file_path,
                now()->addMinutes(5)
            );
        } catch (\Exception) {
            // Fallback: return download route if temporaryUrl not supported
            return route('archives.download', $this);
        }
    }

    public function incrementAccessCount(): void
    {
        $this->increment('access_count');
        $this->update(['last_accessed_at' => now()]);

        // Log activity untuk audit
        activity('archive-accessed')
            ->performedOn($this)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'access_count' => $this->access_count,
            ])
            ->log('Archive file accessed');
    }

    public function deleteFile(): bool
    {
        return Storage::disk('minio')->delete($this->file_path);
    }
}
