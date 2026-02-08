<?php

namespace App\Filament\Resources\ArchiveResource\Pages;

use App\Filament\Resources\ArchiveResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class CreateArchive extends CreateRecord
{
    protected static string $resource = ArchiveResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate document number if not provided
        if (empty($data['document_number'])) {
            $data['document_number'] = $this->generateDocumentNumber();
        }

        // Auto-populate audit fields
        $data['uploaded_by'] = Auth::id();
        $data['uploaded_ip'] = request()->ip();
        $data['uploaded_user_agent'] = request()->userAgent();

        // Extract file metadata if file uploaded
        if (!empty($data['file_path'])) {
            $data = $this->extractFileMetadata($data);
        }

        return $data;
    }

    protected function generateDocumentNumber(): string
    {
        return sprintf(
            'ARC-%s-%s',
            now()->format('Ymd'),
            strtoupper(Str::random(6))
        );
    }

    protected function extractFileMetadata(array $data): array
    {
        $filePath = $data['file_path'];
        $disk = Storage::disk('minio');

        if (!$disk->exists($filePath)) {
            return $data;
        }

        $data['original_filename'] = basename($filePath);
        $data['file_name'] = basename($filePath);
        $data['file_size'] = $disk->size($filePath);

        // Get mime type from file extension
        $fileContents = $disk->get($filePath);
        $tempPath = sys_get_temp_dir() . '/' . basename($filePath);
        file_put_contents($tempPath, $fileContents);
        $data['mime_type'] = File::mimeType($tempPath);
        unlink($tempPath);

        // Generate SHA-256 hash
        $fileContents = $disk->get($filePath);
        $data['file_hash'] = hash('sha256', $fileContents);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
