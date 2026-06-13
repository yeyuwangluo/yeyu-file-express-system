<?php

namespace App\Jobs;

use App\Models\SharedFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DeleteStoredFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $fileId) {}

    public function handle(): void
    {
        $file = SharedFile::withTrashed()->find($this->fileId);
        if (! $file) {
            return;
        }

        Storage::disk($file->disk)->delete($file->path);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('Stored file delete job failed', ['file_id' => $this->fileId, 'message' => $exception->getMessage()]);
    }
}
