<?php

namespace App\Console\Commands;

use App\Models\DailyStat;
use App\Models\FileDownload;
use App\Models\FileUpload;
use App\Models\SharedFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateDailyStats extends Command
{
    protected $signature = 'xiaoxin-file-express:daily-stats {date?}';

    protected $description = 'Generate Xiaoxin File Express daily statistics.';

    public function handle(): int
    {
        $date = $this->argument('date') ?: today()->toDateString();

        $uploads = FileUpload::query()->whereDate('created_at', $date)->where('success', true);
        $downloads = FileDownload::query()->whereDate('created_at', $date)->where('success', true);

        DailyStat::query()->updateOrCreate(
            ['date' => $date],
            [
                'upload_count' => (clone $uploads)->count(),
                'download_count' => (clone $downloads)->count(),
                'upload_size' => (int) (clone $uploads)->sum('size'),
                'download_size' => (int) (clone $downloads)->sum('bytes'),
                'active_files' => SharedFile::query()->where('status', 'active')->where('expires_at', '>', now())->count(),
                'expired_files' => SharedFile::query()->where('status', 'expired')->orWhere('expires_at', '<=', now())->count(),
            ],
        );

        Log::info('Daily stats generated', ['date' => $date]);
        $this->info("Generated stats for {$date}.");

        return self::SUCCESS;
    }
}
