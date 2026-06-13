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

class ComputeFileHash implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $fileId) {}

    public function handle(): void
    {
        $file = SharedFile::query()->find($this->fileId);
        if (! $file || ! Storage::disk($file->disk)->exists($file->path)) {
            return;
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);
        if ($stream === false) {
            return;
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        $file->update(['sha256' => hash_final($context)]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('File hash job failed', ['file_id' => $this->fileId, 'message' => $exception->getMessage()]);
    }
}
