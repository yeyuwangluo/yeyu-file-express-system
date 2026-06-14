<?php

namespace App\Console\Commands;

use App\Jobs\DeleteStoredFile;
use App\Models\ChunkedUpload;
use App\Models\SharedFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CleanupExpiredFiles extends Command
{
    protected $signature = 'yeyu-file-express:cleanup-expired-files';

    protected $description = 'Delete expired uploaded files from storage and mark them expired.';

    public function handle(): int
    {
        $deleted = 0;
        $expiredUploads = 0;

        SharedFile::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->chunkById(100, function ($files) use (&$deleted): void {
                foreach ($files as $file) {
                    try {
                        DeleteStoredFile::dispatch($file->id);
                        $file->update(['status' => 'expired']);
                        $deleted++;
                    } catch (\Throwable $exception) {
                        Log::warning('Expired file cleanup failed', [
                            'file_id' => $file->id,
                            'path' => $file->path,
                            'message' => $exception->getMessage(),
                        ]);
                    }
                }
            });

        if (Schema::hasTable('chunked_uploads')) {
            ChunkedUpload::query()
                ->where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->chunkById(100, function ($uploads) use (&$expiredUploads): void {
                    foreach ($uploads as $upload) {
                        try {
                            Storage::disk($upload->disk)->deleteDirectory($upload->directory);
                            $upload->update(['status' => 'expired']);
                            $expiredUploads++;
                        } catch (\Throwable $exception) {
                            Log::warning('Expired chunked upload cleanup failed', [
                                'upload_id' => $upload->upload_id,
                                'directory' => $upload->directory,
                                'message' => $exception->getMessage(),
                            ]);
                        }
                    }
                });
        }

        Log::info('Expired file cleanup finished', ['deleted' => $deleted, 'expired_uploads' => $expiredUploads]);
        $this->info("Deleted {$deleted} expired files and {$expiredUploads} expired chunked uploads.");

        return self::SUCCESS;
    }
}
