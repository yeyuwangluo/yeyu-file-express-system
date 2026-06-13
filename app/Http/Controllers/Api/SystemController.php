<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\DailyStat;
use App\Models\FileDownload;
use App\Models\FileUpload;
use App\Models\HealthCheck;
use App\Models\LanSession;
use App\Models\AIScanLog;
use App\Models\AuditLog;
use App\Models\SharedFile;
use App\Support\ApiEnvelope;
use App\Support\XiaoxinFileExpressSettings;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SystemController extends Controller
{
    public function config(): JsonResponse
    {
        return ApiEnvelope::ok(XiaoxinFileExpressSettings::configPayload(), '获取配置成功');
    }

    public function announcements(): JsonResponse
    {
        $items = Announcement::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('start_at')->orWhere('start_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('end_at')->orWhere('end_at', '>=', now());
            })
            ->orderByDesc('priority')
            ->latest()
            ->get()
            ->map(fn (Announcement $announcement): array => [
                'id' => (string) $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'type' => $announcement->type,
                'priority' => $announcement->priority,
                'startAt' => $announcement->start_at ? $announcement->start_at->toJSON() : null,
                'endAt' => $announcement->end_at ? $announcement->end_at->toJSON() : null,
                'isActive' => $announcement->is_active,
                'createdAt' => $announcement->created_at ? $announcement->created_at->toJSON() : null,
                'updatedAt' => $announcement->updated_at ? $announcement->updated_at->toJSON() : null,
            ])
            ->values();

        return ApiEnvelope::ok($items, '获取公告列表成功');
    }

    public function status(): JsonResponse
    {
        $payload = Cache::store('file')->remember('xiaoxin-file-express:status-payload', now()->addSeconds(15), function (): array {
            return $this->buildStatusPayload();
        });

        return ApiEnvelope::ok($payload, '获取状态成功');
    }

    private function buildStatusPayload(): array
    {
        $started = microtime(true);
        $dbStarted = microtime(true);
        $databaseAvailable = true;
        $storageStatus = 'ok';
        $error = null;

        try {
            DB::select('select 1');
            $dbResponseTime = (int) round((microtime(true) - $dbStarted) * 1000);
        } catch (\Throwable $exception) {
            $databaseAvailable = false;
            $dbResponseTime = 0;
            $storageStatus = 'degraded';
            $error = $exception->getMessage();
        }

        try {
            Storage::disk(config('filesystems.default', 'local'))->exists('.');
        } catch (\Throwable $exception) {
            $storageStatus = 'error';
            $error = $exception->getMessage();
        }

        $storageWritable = [
            'storageApp' => is_writable(storage_path('app')),
            'frameworkCache' => is_writable(storage_path('framework/cache')),
            'logs' => is_writable(storage_path('logs')),
        ];
        if (in_array(false, $storageWritable, true)) {
            $storageStatus = 'degraded';
            $error = $error ?: '本地存储目录存在不可写项';
        }

        $responseTime = (int) round((microtime(true) - $started) * 1000);
        $status = $error ? 'degraded' : 'healthy';
        $storageUsed = $databaseAvailable ? (int) SharedFile::query()->sum('size') : 0;
        $storageDirectoryUsed = $this->localStorageDirectorySize();
        $storageLimit = (int) config('xiaoxin_file_express.storage_limit');

        $checkPayload = [
            'status' => $status,
            'response_time' => $responseTime,
            'db_response_time' => $dbResponseTime,
            'storage_status' => $storageStatus,
            'error_message' => $error,
            'checked_at' => now(),
            'created_at' => now(),
        ];

        $check = new HealthCheck($checkPayload);
        if ($databaseAvailable) {
            try {
                $check = HealthCheck::query()->create($checkPayload);
            } catch (\Throwable $exception) {
                $databaseAvailable = false;
                $error = $error ?: $exception->getMessage();
                $check->forceFill(['status' => 'degraded', 'error_message' => $error]);
            }
        }

        $recentChecks = $databaseAvailable
            ? HealthCheck::query()->latest('checked_at')->limit(12)->get()
            : collect([$check]);
        $daily = $databaseAvailable
            ? DailyStat::query()->orderByDesc('date')->limit(7)->get()->reverse()->values()
            : collect();
        $healthyCount = $recentChecks->where('status', 'healthy')->count();
        $totalChecks = max(1, $recentChecks->count());

        $todayUploadCount = $databaseAvailable ? (int) FileUpload::query()->whereDate('created_at', today())->where('success', true)->count() : 0;
        $todayDownloadCount = $databaseAvailable ? (int) FileDownload::query()->whereDate('created_at', today())->where('success', true)->count() : 0;
        $todayUploadSize = $databaseAvailable ? (int) FileUpload::query()->whereDate('created_at', today())->where('success', true)->sum('size') : 0;
        $todayDownloadSize = $databaseAvailable ? (int) FileDownload::query()->whereDate('created_at', today())->where('success', true)->sum('bytes') : 0;
        $last7dUploadCount = $databaseAvailable ? (int) FileUpload::query()->where('created_at', '>=', now()->subDays(7))->where('success', true)->count() : 0;
        $last7dDownloadCount = $databaseAvailable ? (int) FileDownload::query()->where('created_at', '>=', now()->subDays(7))->where('success', true)->count() : 0;
        $last7dUploadSize = $databaseAvailable ? (int) FileUpload::query()->where('created_at', '>=', now()->subDays(7))->where('success', true)->sum('size') : 0;
        $last7dDownloadSize = $databaseAvailable ? (int) FileDownload::query()->where('created_at', '>=', now()->subDays(7))->where('success', true)->sum('bytes') : 0;
        $totalUploadCount = $databaseAvailable ? (int) FileUpload::query()->where('success', true)->count() : 0;
        $totalDownloadCount = $databaseAvailable ? (int) FileDownload::query()->where('success', true)->count() : 0;
        $totalUploadSize = $databaseAvailable ? (int) FileUpload::query()->where('success', true)->sum('size') : 0;
        $totalDownloadSize = $databaseAvailable ? (int) FileDownload::query()->where('success', true)->sum('bytes') : 0;
        $totalFiles = $databaseAvailable ? (int) SharedFile::query()->count() : 0;
        $expiredFiles = $databaseAvailable ? (int) SharedFile::query()->where('expires_at', '<=', now())->count() : 0;
        $activeFiles = $databaseAvailable ? (int) SharedFile::query()->where('status', 'active')->where('expires_at', '>', now())->count() : 0;
        $lanSessions = $databaseAvailable ? (int) LanSession::query()->count() : 0;
        $jobsCount = $databaseAvailable && Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : null;
        $failedJobsCount = $databaseAvailable && Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : null;
        $actionableFailedJobsCount = $databaseAvailable ? $this->actionableFailedScanJobsCount() : 0;
        $queueHeartbeatAt = Setting::valueFor('queue', 'worker_heartbeat_at', null);
        $aiFailures24h = $databaseAvailable ? (int) AIScanLog::query()->where('skipped', true)->where('created_at', '>=', now()->subDay())->count() : 0;
        $lastOpsAt = $databaseAvailable ? AuditLog::query()
            ->whereIn('action', ['file.rescan', 'file.bulk_rescan', 'failed_job.retry_scan', 'maintenance.cleanup_lan', 'maintenance.prune_logs'])
            ->latest('created_at')
            ->value('created_at') : null;
        $aiHealth = $this->aiHealth();
        
        // Virus scan statistics (ClamAV)
        $totalScanClean = $databaseAvailable ? (int) SharedFile::query()->where('scan_status', 'clean')->count() : 0;
        $totalScanInfected = $databaseAvailable ? (int) SharedFile::query()->where('scan_status', 'infected')->count() : 0;
        $totalScanPending = $databaseAvailable ? (int) SharedFile::query()->where('scan_status', 'pending')->count() : 0;
        $totalScanError = $databaseAvailable ? (int) SharedFile::query()->where('scan_status', 'error')->count() : 0;
        $totalScanned = $totalScanClean + $totalScanInfected + $totalScanError;
        
        $todayScanClean = $databaseAvailable ? (int) SharedFile::query()->whereDate('created_at', today())->where('scan_status', 'clean')->count() : 0;
        $todayScanInfected = $databaseAvailable ? (int) SharedFile::query()->whereDate('created_at', today())->where('scan_status', 'infected')->count() : 0;
        $todayScanPending = $databaseAvailable ? (int) SharedFile::query()->whereDate('created_at', today())->where('scan_status', 'pending')->count() : 0;
        $todayScanError = $databaseAvailable ? (int) SharedFile::query()->whereDate('created_at', today())->where('scan_status', 'error')->count() : 0;
        $todayScanned = $todayScanClean + $todayScanInfected + $todayScanError;
        
        $last7dScanClean = $databaseAvailable ? (int) SharedFile::query()->where('created_at', '>=', now()->subDays(7))->where('scan_status', 'clean')->count() : 0;
        $last7dScanInfected = $databaseAvailable ? (int) SharedFile::query()->where('created_at', '>=', now()->subDays(7))->where('scan_status', 'infected')->count() : 0;
        $last7dScanPending = $databaseAvailable ? (int) SharedFile::query()->where('created_at', '>=', now()->subDays(7))->where('scan_status', 'pending')->count() : 0;
        $last7dScanError = $databaseAvailable ? (int) SharedFile::query()->where('created_at', '>=', now()->subDays(7))->where('scan_status', 'error')->count() : 0;
        $last7dScanned = $last7dScanClean + $last7dScanInfected + $last7dScanError;
        
        $infectionRate = $totalScanned > 0 ? round($totalScanInfected / $totalScanned * 100, 2) : 0;
        $todayInfectionRate = $todayScanned > 0 ? round($todayScanInfected / $todayScanned * 100, 2) : 0;
        $last7dInfectionRate = $last7dScanned > 0 ? round($last7dScanInfected / $last7dScanned * 100, 2) : 0;

        // Malware scan statistics (Custom scanner)
        $totalMalwareClean = $databaseAvailable ? (int) SharedFile::query()->where('malware_scan_passed', true)->count() : 0;
        $totalMalwareInfected = $databaseAvailable ? (int) SharedFile::query()->where('malware_scan_passed', false)->whereNotNull('malware_scan_checked_at')->count() : 0;
        $totalMalwarePending = $databaseAvailable ? (int) SharedFile::query()->whereNull('malware_scan_checked_at')->count() : 0;
        $totalMalwareScanned = $totalMalwareClean + $totalMalwareInfected;
        
        $todayMalwareClean = $databaseAvailable ? (int) SharedFile::query()->whereDate('created_at', today())->where('malware_scan_passed', true)->count() : 0;
        $todayMalwareInfected = $databaseAvailable ? (int) SharedFile::query()->whereDate('created_at', today())->where('malware_scan_passed', false)->whereNotNull('malware_scan_checked_at')->count() : 0;
        $todayMalwarePending = $databaseAvailable ? (int) SharedFile::query()->whereDate('created_at', today())->whereNull('malware_scan_checked_at')->count() : 0;
        $todayMalwareScanned = $todayMalwareClean + $todayMalwareInfected;
        
        $last7dMalwareClean = $databaseAvailable ? (int) SharedFile::query()->where('created_at', '>=', now()->subDays(7))->where('malware_scan_passed', true)->count() : 0;
        $last7dMalwareInfected = $databaseAvailable ? (int) SharedFile::query()->where('created_at', '>=', now()->subDays(7))->where('malware_scan_passed', false)->whereNotNull('malware_scan_checked_at')->count() : 0;
        $last7dMalwarePending = $databaseAvailable ? (int) SharedFile::query()->where('created_at', '>=', now()->subDays(7))->whereNull('malware_scan_checked_at')->count() : 0;
        $last7dMalwareScanned = $last7dMalwareClean + $last7dMalwareInfected;
        
        $malwareInfectionRate = $totalMalwareScanned > 0 ? round($totalMalwareInfected / $totalMalwareScanned * 100, 2) : 0;
        $todayMalwareInfectionRate = $todayMalwareScanned > 0 ? round($todayMalwareInfected / $todayMalwareScanned * 100, 2) : 0;
        $last7dMalwareInfectionRate = $last7dMalwareScanned > 0 ? round($last7dMalwareInfected / $last7dMalwareScanned * 100, 2) : 0;


        $payload = [
            'current' => [
                'status' => $check->status,
                'responseTime' => $check->response_time,
                'dbResponseTime' => $check->db_response_time,
                'storageStatus' => $check->storage_status,
                'storageUsage' => [
                    'used' => $storageUsed,
                    'usedByFiles' => $storageUsed,
                    'usedByStorageDirectory' => $storageDirectoryUsed,
                    'limit' => $storageLimit,
                    'percentage' => $storageLimit > 0 ? round($storageUsed / $storageLimit * 100, 2) : 0,
                    'directoryPercentage' => $storageLimit > 0 ? round($storageDirectoryUsed / $storageLimit * 100, 2) : 0,
                    'storageType' => config('filesystems.default', 'local') === 's3' ? 'cloud' : 'local',
                ],
                'lastChecked' => ApiEnvelope::timestamp(),
            ],
            'uptime' => [
                'percentage' => round($healthyCount / $totalChecks * 100, 2),
                'daily' => $daily->map(fn (DailyStat $stat): array => [
                    'date' => $stat->date->toDateString(),
                    'uptime' => 100,
                    'checks' => 1,
                ])->values()->all(),
            ],
            'recentChecks' => $recentChecks->map(fn (HealthCheck $item): array => [
                'id' => (string) $item->id,
                'status' => $item->status,
                'responseTime' => $item->response_time,
                'dbResponseTime' => $item->db_response_time,
                'storageStatus' => $item->storage_status,
                'errorMessage' => $item->error_message,
                'checkedAt' => $item->checked_at ? $item->checked_at->valueOf() : null,
            ])->values()->all(),
            'recentSummary' => [
                'totalChecks' => $recentChecks->count(),
                'healthyCount' => $healthyCount,
                'degradedCount' => $recentChecks->where('status', 'degraded')->count(),
                'downCount' => $recentChecks->where('status', 'down')->count(),
                'avgResponseTime' => (int) round($recentChecks->avg('response_time') ?? 0),
                'avgDbResponseTime' => (int) round($recentChecks->avg('db_response_time') ?? 0),
                'peakResponseTime' => (int) ($recentChecks->max('response_time') ?? 0),
                'peakDbResponseTime' => (int) ($recentChecks->max('db_response_time') ?? 0),
            ],
            'usage' => [
                'today' => [
                    'uploadCount' => $todayUploadCount,
                    'downloadCount' => $todayDownloadCount,
                    'uploadSize' => $todayUploadSize,
                    'downloadSize' => $todayDownloadSize,
                ],
                'last7d' => [
                    'uploadCount' => $last7dUploadCount,
                    'downloadCount' => $last7dDownloadCount,
                    'uploadSize' => $last7dUploadSize,
                    'downloadSize' => $last7dDownloadSize,
                ],
                'total' => [
                    'uploadCount' => $totalUploadCount,
                    'downloadCount' => $totalDownloadCount,
                    'uploadSize' => $totalUploadSize,
                    'downloadSize' => $totalDownloadSize,
                    'totalFiles' => $totalFiles,
                    'expiredFiles' => $expiredFiles,
                ],
            ],

            'virusScan' => [
                'total' => [
                    'clean' => $totalScanClean,
                    'infected' => $totalScanInfected,
                    'pending' => $totalScanPending,
                    'error' => $totalScanError,
                    'scanned' => $totalScanned,
                    'infectionRate' => $infectionRate,
                ],
                'today' => [
                    'clean' => $todayScanClean,
                    'infected' => $todayScanInfected,
                    'pending' => $todayScanPending,
                    'error' => $todayScanError,
                    'scanned' => $todayScanned,
                    'infectionRate' => $todayInfectionRate,
                ],
                'last7d' => [
                    'clean' => $last7dScanClean,
                    'infected' => $last7dScanInfected,
                    'pending' => $last7dScanPending,
                    'error' => $last7dScanError,
                    'scanned' => $last7dScanned,
                    'infectionRate' => $last7dInfectionRate,
                ],
            ],

            'malwareScan' => [
                'total' => [
                    'clean' => $totalMalwareClean,
                    'infected' => $totalMalwareInfected,
                    'pending' => $totalMalwarePending,
                    'scanned' => $totalMalwareScanned,
                    'infectionRate' => $malwareInfectionRate,
                ],
                'today' => [
                    'clean' => $todayMalwareClean,
                    'infected' => $todayMalwareInfected,
                    'pending' => $todayMalwarePending,
                    'scanned' => $todayMalwareScanned,
                    'infectionRate' => $todayMalwareInfectionRate,
                ],
                'last7d' => [
                    'clean' => $last7dMalwareClean,
                    'infected' => $last7dMalwareInfected,
                    'pending' => $last7dMalwarePending,
                    'scanned' => $last7dMalwareScanned,
                    'infectionRate' => $last7dMalwareInfectionRate,
                ],
            ],

            'cleanup' => [
                'lastDeletedCount' => 0,
                'lastAt' => Setting::valueFor('maintenance', 'lan_cleanup_last_at', null),
                'lanCleanupSummary' => Setting::valueFor('maintenance', 'lan_cleanup_last_summary', null),
                'logPruneLastAt' => Setting::valueFor('maintenance', 'log_prune_last_at', null),
                'logPruneSummary' => Setting::valueFor('maintenance', 'log_prune_last_summary', null),
            ],
            'operations' => [
                'healthScore' => $this->healthScore($databaseAvailable, $storageStatus, $jobsCount, $actionableFailedJobsCount, $aiFailures24h, $aiHealth['status']),
                'queue' => [
                    'pendingJobs' => $jobsCount,
                    'failedJobs' => $failedJobsCount,
                    'actionableFailedJobs' => $actionableFailedJobsCount,
                    'workerHeartbeatAt' => $queueHeartbeatAt,
                    'workerHeartbeatFresh' => $queueHeartbeatAt ? strtotime((string) $queueHeartbeatAt) >= now()->subMinutes(3)->timestamp : false,
                    'lastOperationAt' => $lastOpsAt,
                ],
                'ai' => $aiHealth,
                'storageWritable' => $storageWritable,
            ],
            'localRuntime' => [
                'startedAt' => null,
                'uploadedFiles' => $totalFiles,
                'activeFiles' => $activeFiles,
                'lanSessions' => $lanSessions,
            ],
        ];

        return $payload;
    }

    public function termsContent(): JsonResponse
    {
        return ApiEnvelope::ok([
            'terms' => Setting::valueFor('content', 'terms') ?? '',
            'privacy' => Setting::valueFor('content', 'privacy') ?? '',
        ], '获取成功');
    }

    public function appDownloadConfig(): JsonResponse
    {
        return ApiEnvelope::ok(XiaoxinFileExpressSettings::appDownload(), '获取成功');
    }

    public function appDownload(string $platform)
    {
        $config = XiaoxinFileExpressSettings::appDownload();
        $key = $platform === 'ios' ? 'ios' : 'android';
        $enabledKey = $key.'Enabled';
        $urlKey = $key.'DownloadUrl';

        $downloadUrl = trim((string) ($config[$urlKey] ?? ''));
        if (! $config['enabled'] || ! ($config[$enabledKey] ?? false) || ! $this->isAllowedDownloadUrl($downloadUrl)) {
            return ApiEnvelope::error('当前平台下载暂未开放', 404, 404);
        }

        return redirect()->away($downloadUrl);
    }

    private function isAllowedDownloadUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true);
    }

    private function localStorageDirectorySize(): int
    {
        if (config('filesystems.default', 'local') !== 'local') {
            return 0;
        }

        $path = storage_path('app');
        if (! is_dir($path)) {
            return 0;
        }

        $size = 0;

        try {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable) {
            return 0;
        }

        return $size;
    }

    private function aiHealth(): array
    {
        $enabled = (bool) Setting::valueFor('ai_scan', 'ai_scan_enabled', false);
        $apiUrl = trim((string) Setting::valueFor('ai_scan', 'ai_scan_api_url', ''));
        $apiKey = trim((string) Setting::valueFor('ai_scan', 'ai_scan_api_key', ''));

        if (! $enabled) {
            return ['enabled' => false, 'status' => 'disabled', 'responseTime' => null, 'message' => 'AI 扫描未启用'];
        }
        if ($apiUrl === '' || $apiKey === '') {
            return ['enabled' => true, 'status' => 'degraded', 'responseTime' => null, 'message' => 'AI 扫描配置不完整'];
        }

        $started = microtime(true);
        try {
            $modelsUrl = preg_replace('#/chat/completions$#', '/models', $apiUrl) ?: $apiUrl;
            $response = Http::timeout(5)->withToken($apiKey)->get($modelsUrl);
            return [
                'enabled' => true,
                'status' => $response->successful() ? 'healthy' : 'degraded',
                'responseTime' => (int) round((microtime(true) - $started) * 1000),
                'message' => $response->successful() ? 'AI 服务可用' : 'AI 服务响应异常：HTTP '.$response->status(),
            ];
        } catch (\Throwable $exception) {
            return [
                'enabled' => true,
                'status' => 'degraded',
                'responseTime' => (int) round((microtime(true) - $started) * 1000),
                'message' => 'AI 服务检查失败：'.$exception->getMessage(),
            ];
        }
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
                $payload = (string) $job->payload;
                $fileId = null;
                if (preg_match('/fileId";i:(\d+)/', $payload, $matches) === 1 || preg_match('/fileId.*?i:(\d+)/s', $payload, $matches) === 1) {
                    $fileId = (int) $matches[1];
                }

                return $fileId !== null && SharedFile::query()->whereKey($fileId)->exists();
            })
            ->count();
    }

    private function healthScore(bool $databaseAvailable, string $storageStatus, ?int $jobsCount, ?int $failedJobsCount, int $aiFailures24h, string $aiStatus): int
    {
        $score = 100;
        if (! $databaseAvailable) {
            $score -= 35;
        }
        if ($storageStatus !== 'ok') {
            $score -= 20;
        }
        if (($failedJobsCount ?? 0) > 0) {
            $score -= min(20, (int) $failedJobsCount * 3);
        }
        if (($jobsCount ?? 0) > 100) {
            $score -= 10;
        }
        if ($aiFailures24h > 0) {
            $score -= min(15, $aiFailures24h * 2);
        }
        if ($aiStatus === 'degraded') {
            $score -= 10;
        }

        return max(0, $score);
    }
}
