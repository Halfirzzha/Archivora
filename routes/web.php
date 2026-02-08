<?php

use App\Models\Archive;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

// Health check endpoint for Docker monitoring (no auth required)
Route::get('/health', function () {
    try {
        // Check database connection
        DB::connection()->getPdo();

        // Check Redis connection (if configured)
        if (config('cache.default') === 'redis') {
            Cache::get('health_check');
        }

        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'database' => 'connected',
            'cache' => 'connected',
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
            'timestamp' => now()->toIso8601String(),
        ], 503);
    }
})->name('health');

Route::get('/', function () {
    return view('welcome');
});

// Protected archive download route (requires auth + authorization)
Route::middleware(['auth'])->group(function () {
    Route::get('/archives/{archive}/download', function (Archive $archive) {
        // Authorization check via Policy
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user || !$user->can('download', $archive)) {
            abort(403, 'You are not authorized to download this file.');
        }

        // Log download activity
        $archive->incrementAccessCount();

        // Stream file dari MinIO private storage
        return response()->streamDownload(function () use ($archive) {
            echo Storage::disk('minio')->get($archive->file_path);
        }, $archive->original_filename, [
            'Content-Type' => $archive->mime_type,
            'Content-Length' => $archive->file_size,
            'Content-Disposition' => 'attachment; filename="' . $archive->original_filename . '"',
        ]);
    })->name('archives.download');
});
