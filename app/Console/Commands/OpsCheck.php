<?php

namespace App\Console\Commands;

use App\Models\AIScanLog;
use App\Models\SharedFile;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class OpsCheck extends Command
{
    protected $signature = 'yeyu-file-express:ops-check {--json : Output JSON} {--strict : Return failure when actionable issues exist} {--record : Persist the latest check result}';

    protected $description = 'Check operational readiness for queue, scans, and risk review.';

    public function handle(): int
    {
        $heartbeatAt = Setting::valueFor('queue', 'worker_heartbeat_at', null);
        $heartbeatFresh = $heartbeatAt ? strtotime((string) $heartbeatAt) >= now()->subMinutes(3)->timestamp : false;
        $riskReviewStates = $this->riskReviewStates();
        $riskFileIds = SharedFile::query()->where('malware_scan_passed', false)->pluck('id')->map(fn ($id): int => (int) $id);
        $riskPending = $riskFileIds->filter(fn (int $id): bool => ($riskReviewStates[$id]['status'] ?? 'pending') === 'pending')->count();
        $riskOverdue = $this->riskReviewOverdueCount($riskReviewStates);

        $summary = [
            'status' => 'ok',
            'checked_at' => now()->toDateTimeString(),
            'queue' => [
                'pending_jobs' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'failed_jobs' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'actionable_failed_jobs' => $this->actionableFailedScanJobsCount(),
                'worker_heartbeat_at' => $heartbeatAt,
                'worker_heartbeat_fresh' => $heartbeatFresh,
            ],
            'scan' => [
                'malware_scan_pending' => SharedFile::query()->whereNull('malware_scan_checked_at')->count(),
                'ai_failures_24h' => AIScanLog::query()->where('skipped', true)->where('created_at', '>=', now()->subDay())->count(),
            ],
            'risk_review' => [
                'risk_files' => $riskFileIds->count(),
                'pending_review' => $riskPending,
                'overdue_review' => $riskOverdue,
            ],
            'backup' => $this->backupStatus(),
        ];
        $issues = $this->issues($summary);
        $summary['status'] = $issues === [] ? 'ok' : 'attention';
        $summary['issues'] = $issues;

        if ($this->option('record')) {
            $previousStatus = (string) Setting::valueFor('ops_check', 'last_status', 'unknown');
            Setting::query()->updateOrCreate(['group' => 'ops_check', 'key' => 'last_checked_at'], ['value' => $summary['checked_at'], 'type' => 'string']);
            Setting::query()->updateOrCreate(['group' => 'ops_check', 'key' => 'last_status'], ['value' => $summary['status'], 'type' => 'string']);
            Setting::query()->updateOrCreate(['group' => 'ops_check', 'key' => 'last_result'], ['value' => json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'type' => 'json']);
            Setting::query()->updateOrCreate(['group' => 'ops_check', 'key' => 'history'], ['value' => json_encode($this->updatedHistory($summary), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'type' => 'json']);
            $this->sendAlertIfNeeded($summary, $previousStatus);
        }

        if ($this->option('json')) {
            $this->line(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        } else {
            $this->info('Queue pending: '.$summary['queue']['pending_jobs']);
            $this->info('Actionable failed jobs: '.$summary['queue']['actionable_failed_jobs'].' / total '.$summary['queue']['failed_jobs']);
            $this->info('Queue heartbeat: '.($heartbeatFresh ? 'fresh' : 'stale').' ('.($heartbeatAt ?: 'n/a').')');
            $this->info('Malware scan pending: '.$summary['scan']['malware_scan_pending']);
            $this->info('AI failures in 24h: '.$summary['scan']['ai_failures_24h']);
            $this->info('Risk review pending: '.$summary['risk_review']['pending_review'].' / total '.$summary['risk_review']['risk_files']);
        }

        return $this->option('strict') && $issues !== [] ? self::FAILURE : self::SUCCESS;
    }

    private function issues(array $summary): array
    {
        $issues = [];
        if (! ($summary['queue']['worker_heartbeat_fresh'] ?? false)) {
            $issues[] = 'queue_heartbeat_stale';
        }
        if (($summary['queue']['actionable_failed_jobs'] ?? 0) > 0) {
            $issues[] = 'actionable_failed_jobs';
        }
        if (($summary['scan']['malware_scan_pending'] ?? 0) > 0) {
            $issues[] = 'malware_scan_pending';
        }
        if (($summary['scan']['ai_failures_24h'] ?? 0) > 0) {
            $issues[] = 'ai_failures_24h';
        }
        if (($summary['risk_review']['overdue_review'] ?? 0) > 0) {
            $issues[] = 'risk_review_sla_overdue';
        }

        return $issues;
    }

    private function updatedHistory(array $summary): array
    {
        $history = Setting::valueFor('ops_check', 'history', []);
        if (! is_array($history)) {
            $history = json_decode((string) $history, true) ?: [];
        }
        $history[] = [
            'checked_at' => $summary['checked_at'],
            'status' => $summary['status'],
            'issues' => $summary['issues'],
            'pending_jobs' => $summary['queue']['pending_jobs'] ?? 0,
            'actionable_failed_jobs' => $summary['queue']['actionable_failed_jobs'] ?? 0,
            'malware_scan_pending' => $summary['scan']['malware_scan_pending'] ?? 0,
            'ai_failures_24h' => $summary['scan']['ai_failures_24h'] ?? 0,
            'risk_review_pending' => $summary['risk_review']['pending_review'] ?? 0,
            'risk_review_overdue' => $summary['risk_review']['overdue_review'] ?? 0,
        ];

        return array_slice($history, -288);
    }

    private function sendAlertIfNeeded(array $summary, string $previousStatus): void
    {
        if (! $this->settingEnabled(Setting::valueFor('ops_alert', 'enabled', false))) {
            return;
        }

        $webhookUrl = trim((string) Setting::valueFor('ops_alert', 'webhook_url', ''));
        if (! $this->webhookUrlAllowed($webhookUrl)) {
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_error'], ['value' => 'Webhook URL is not allowed', 'type' => 'string']);

            return;
        }

        $status = (string) ($summary['status'] ?? 'ok');
        if ($status === 'ok' && $previousStatus === 'attention') {
            $this->sendRecovery($webhookUrl, $summary);

            return;
        }
        if ($status !== 'attention') {
            return;
        }

        $fingerprint = sha1(json_encode($summary['issues'] ?? [], JSON_UNESCAPED_UNICODE));
        $lastFingerprint = (string) Setting::valueFor('ops_alert', 'last_fingerprint', '');
        $lastAlertedAt = Setting::valueFor('ops_alert', 'last_alerted_at', null);
        $minInterval = max(5, (int) Setting::valueFor('ops_alert', 'min_interval_minutes', 60));
        if ($fingerprint === $lastFingerprint && $lastAlertedAt && strtotime((string) $lastAlertedAt) >= now()->subMinutes($minInterval)->timestamp) {
            return;
        }

        try {
            $response = Http::timeout(5)->acceptJson()->post($webhookUrl, [
                'title' => '叶宇文件快递运维自检告警',
                'status' => $summary['status'],
                'checked_at' => $summary['checked_at'],
                'issues' => $summary['issues'] ?? [],
                'queue' => $summary['queue'] ?? [],
                'scan' => $summary['scan'] ?? [],
                'risk_review' => $summary['risk_review'] ?? [],
            ]);

            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_alerted_at'], ['value' => now()->toDateTimeString(), 'type' => 'string']);
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_fingerprint'], ['value' => $fingerprint, 'type' => 'string']);
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_status_code'], ['value' => (string) $response->status(), 'type' => 'int']);
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_error'], ['value' => $response->successful() ? '' : mb_substr($response->body(), 0, 500), 'type' => 'string']);
        } catch (\Throwable $e) {
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_error'], ['value' => mb_substr($e->getMessage(), 0, 500), 'type' => 'string']);
        }
    }

    private function sendRecovery(string $webhookUrl, array $summary): void
    {
        try {
            $response = Http::timeout(5)->acceptJson()->post($webhookUrl, [
                'title' => '叶宇文件快递运维自检恢复',
                'status' => 'ok',
                'checked_at' => $summary['checked_at'],
                'issues' => [],
                'queue' => $summary['queue'] ?? [],
                'scan' => $summary['scan'] ?? [],
                'risk_review' => $summary['risk_review'] ?? [],
            ]);

            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_recovered_at'], ['value' => now()->toDateTimeString(), 'type' => 'string']);
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_recovery_status_code'], ['value' => (string) $response->status(), 'type' => 'int']);
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_recovery_error'], ['value' => $response->successful() ? '' : mb_substr($response->body(), 0, 500), 'type' => 'string']);
        } catch (\Throwable $e) {
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_recovery_error'], ['value' => mb_substr($e->getMessage(), 0, 500), 'type' => 'string']);
        }
    }

    private function webhookUrlAllowed(string $webhookUrl): bool
    {
        $parts = parse_url($webhookUrl);
        if (($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return true;
    }

    private function settingEnabled(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    private function riskReviewStates(): array
    {
        return Setting::query()
            ->where('group', 'risk_review')
            ->get(['key', 'value'])
            ->mapWithKeys(function (Setting $setting): array {
                $value = $setting->value;
                $decoded = is_array($value) ? $value : json_decode((string) $value, true);

                return [(int) $setting->key => is_array($decoded) ? $decoded : []];
            })
            ->all();
    }

    private function riskReviewOverdueCount(array $riskReviewStates): int
    {
        return SharedFile::query()
            ->where('malware_scan_passed', false)
            ->whereNotNull('malware_scan_checked_at')
            ->where('malware_scan_checked_at', '<=', now()->subDay())
            ->get(['id'])
            ->filter(fn (SharedFile $file): bool => ($riskReviewStates[$file->id]['status'] ?? 'pending') === 'pending')
            ->count();
    }

    private function backupStatus(): array
    {
        $candidates = [storage_path('app/backups'), storage_path('backups'), base_path('database/backups')];
        $latest = null;
        foreach ($candidates as $path) {
            if (! File::isDirectory($path)) {
                continue;
            }
            foreach (File::files($path) as $file) {
                $mtime = $file->getMTime();
                if ($latest === null || $mtime > $latest['mtime']) {
                    $latest = ['path' => $file->getPathname(), 'mtime' => $mtime, 'size' => $file->getSize()];
                }
            }
        }

        return [
            'latest_at' => $latest ? date('Y-m-d H:i:s', $latest['mtime']) : null,
            'latest_size' => $latest['size'] ?? null,
            'latest_path' => $latest ? str_replace(base_path().'/', '', $latest['path']) : null,
            'fresh_24h' => $latest ? $latest['mtime'] >= now()->subDay()->timestamp : false,
        ];
    }

    private function actionableFailedScanJobsCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return DB::table('failed_jobs')
            ->where('payload', 'like', '%ScanUploadedFile%')
            ->get(['payload'])
            ->filter(function ($job): bool {
                $fileId = null;
                $payload = (string) $job->payload;
                if (preg_match('/fileId";i:(\d+)/', $payload, $matches) === 1 || preg_match('/fileId.*?i:(\d+)/s', $payload, $matches) === 1) {
                    $fileId = (int) $matches[1];
                }

                return $fileId !== null && SharedFile::query()->whereKey($fileId)->exists();
            })
            ->count();
    }
}
