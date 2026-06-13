<?php

namespace App\Console\Commands;

use App\Jobs\SendSystemAlert;
use App\Models\HealthCheck;
use App\Models\SharedFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckStorageCapacity extends Command
{
    protected $signature = 'xiaoxin-file-express:check-storage';

    protected $description = 'Record storage capacity status for the Xiaoxin File Express system.';

    public function handle(): int
    {
        $used = (int) SharedFile::query()->sum('size');
        $limit = (int) config('xiaoxin_file_express.storage_limit');
        $percentage = $limit > 0 ? round($used / $limit * 100, 2) : 0;
        $status = $percentage >= 90 ? 'degraded' : 'healthy';

        HealthCheck::query()->create([
            'status' => $status,
            'response_time' => 0,
            'db_response_time' => 0,
            'storage_status' => $percentage >= 90 ? 'low_space' : 'ok',
            'error_message' => $percentage >= 90 ? "Storage usage is {$percentage}%" : null,
            'checked_at' => now(),
            'created_at' => now(),
        ]);

        Log::info('Storage capacity checked', compact('used', 'limit', 'percentage', 'status'));
        if ($status === 'degraded') {
            SendSystemAlert::dispatch('warning', 'Storage capacity is running low.', compact('used', 'limit', 'percentage'));
        }

        $this->info("Storage usage {$percentage}%.");

        return self::SUCCESS;
    }
}
