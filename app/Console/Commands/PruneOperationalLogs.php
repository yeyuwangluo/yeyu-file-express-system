<?php

namespace App\Console\Commands;

use App\Models\FileDownload;
use App\Models\FileUpload;
use App\Models\HealthCheck;
use App\Models\RiskDownloadAckLog;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneOperationalLogs extends Command
{
    protected $signature = 'yeyu-file-express:prune-logs {--days=90}';

    protected $description = 'Prune old upload, download, and health check records.';

    public function handle(): int
    {
        $before = now()->subDays((int) $this->option('days'));
        $uploads = FileUpload::query()->where('created_at', '<', $before)->delete();
        $downloads = FileDownload::query()->where('created_at', '<', $before)->delete();
        $healthChecks = HealthCheck::query()->where('created_at', '<', $before)->delete();
        $riskAcks = RiskDownloadAckLog::query()->where('created_at', '<', $before)->delete();

        Log::info('Operational logs pruned', compact('uploads', 'downloads', 'healthChecks', 'riskAcks'));
        $summary = "Pruned uploads={$uploads}, downloads={$downloads}, healthChecks={$healthChecks}, riskAcks={$riskAcks}.";
        Setting::query()->updateOrCreate(['group' => 'maintenance', 'key' => 'log_prune_last_at'], ['value' => now()->toDateTimeString(), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'maintenance', 'key' => 'log_prune_last_summary'], ['value' => $summary, 'type' => 'string']);
        $this->info($summary);

        return self::SUCCESS;
    }
}
