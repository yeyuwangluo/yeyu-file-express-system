<?php

namespace App\Jobs;

use App\Models\BlockedIp;
use App\Models\SharedFile;
use App\Support\XiaoxinFileExpressSettings;
use App\Services\Netdisk123Service;
use App\Services\MalwareScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class ScanUploadedFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $fileId) {}

    public function handle(): void
    {
        $file = SharedFile::query()->find($this->fileId);
        if (! $file) {
            return;
        }

        $config = XiaoxinFileExpressSettings::virusScan();
        if (! (bool) ($config['enabled'] ?? false)) {
            $this->mark($file, 'skipped', 'scan-disabled');
            // 即使病毒扫描被禁用，仍然执行恶意代码扫描
            $this->performMalwareScan($file);
            return;
        }

        $diskConfig = config("filesystems.disks.{$file->disk}", []);
        $driver = $diskConfig['driver'] ?? null;

        // 检查文件是否存在
        if (! Storage::disk($file->disk)->exists($file->path)) {
            $this->mark($file, 'missing', 'storage-file-missing');
            return;
        }

        $scanner = (string) ($config['clamavPath'] ?? 'clamscan');
        $path = null;
        $tempFile = null;
        $cleanupNeeded = false;

        try {
            // 如果不是本地存储，需要先下载到临时文件
            if ($driver !== 'local') {
                // 尝试从存储中下载文件到临时目录
                $content = Storage::disk($file->disk)->get($file->path);
                if ($content === false) {
                    $this->mark($file, 'error', 'failed-to-download-from-storage');
                    return;
                }

                // 创建临时文件
                $tempDir = sys_get_temp_dir();
                $tempFile = $tempDir . '/virus_scan_' . $file->id . '_' . time() . '_' . bin2hex(random_bytes(8));
                if (file_put_contents($tempFile, $content) === false) {
                    $this->mark($file, 'error', 'failed-to-create-temp-file');
                    return;
                }

                $path = $tempFile;
                $cleanupNeeded = true;
            } else {
                // 本地存储，直接使用文件路径
                $path = Storage::disk($file->disk)->path($file->path);
            }

            // 执行病毒扫描
            $process = new Process([$scanner, '--no-summary', $path]);
            $process->setTimeout(max(5, (int) ($config['timeoutSeconds'] ?? 60)));
            $process->run();

            $output = trim($process->getOutput() . "\n" . $process->getErrorOutput());
            $result = mb_substr($output !== '' ? $output : 'no-output', 0, 4000);

            if ($process->isSuccessful()) {
                $this->mark($file, 'clean', $result);
                
                // 病毒扫描通过后，执行恶意代码扫描
                $this->performMalwareScan($file, $path);
                return;
            }

            if ($process->getExitCode() === 1) {
                $file->forceFill([
                    'status' => 'blocked',
                    'scan_status' => 'infected',
                    'scan_result' => $result,
                    'scan_checked_at' => now(),
                    'malware_scan_passed' => false,
                    'malware_scan_checked_at' => now(),
                    'malware_scan_details' => json_encode([
                        'virus_found' => true,
                        'scan_result' => $result
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
                ])->save();
                
                // 将上传者IP加入黑名单24小时
                if ($file->uploader_ip) {
                    BlockedIp::query()->updateOrCreate(
                        [
                            'ip' => $file->uploader_ip,
                            'scope' => 'all',
                        ],
                        [
                            'reason' => '上传病毒文件: ' . $file->original_name,
                            'expires_at' => now()->addHours(24),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                return;
            }

            $this->mark($file, 'error', $result);
            // 病毒扫描错误时也执行恶意代码扫描
            $this->performMalwareScan($file, $path);

        } finally {
            // 清理临时文件
            if ($cleanupNeeded && $tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    /**
     * 执行恶意代码扫描（不隔离文件，仅记录结果）
     */
    private function performMalwareScan(SharedFile $file, ?string $tempPath = null): void
    {
        try {
            $malwareScanner = new MalwareScanner();
            
            // 确定要扫描的文件路径
            $scanPath = $tempPath;
            if ($scanPath === null) {
                $diskConfig = config("filesystems.disks.{$file->disk}", []);
                $driver = $diskConfig['driver'] ?? null;
                
                if ($driver !== 'local') {
                    // 非本地存储，下载到临时文件
                    $content = Storage::disk($file->disk)->get($file->path);
                    if ($content !== false) {
                        $tempDir = sys_get_temp_dir();
                        $scanPath = $tempDir . '/malware_scan_' . $file->id . '_' . time() . '_' . bin2hex(random_bytes(8));
                        file_put_contents($scanPath, $content);
                    }
                } else {
                    // 本地存储，直接使用文件路径
                    $scanPath = Storage::disk($file->disk)->path($file->path);
                }
            }

            if ($scanPath === null || !file_exists($scanPath)) {
                // 无法获取文件路径时按风险文件处理，避免 fail-open
                $file->forceFill([
                    'malware_scan_passed' => false,
                    'malware_scan_checked_at' => now(),
                    'risk_score' => max((int) ($file->risk_score ?? 0), 95),
                    'risk_reasons_json' => array_values(array_unique(array_merge(
                        is_array($file->risk_reasons_json) ? $file->risk_reasons_json : [],
                        ['内容安全扫描无法访问文件，已按高风险处理']
                    ))),
                    'malware_scan_details' => json_encode([
                        'status' => 'error',
                        'reason' => 'file_not_accessible',
                        'fail_closed' => true,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
                ])->save();
                return;
            }

            // 执行恶意代码扫描
            $scanResult = $malwareScanner->scanFile($scanPath, $file->original_name, $file->id);
            $risk = $this->malwareRisk($file, $scanResult);
            
            // 清理临时文件
            if ($tempPath !== $scanPath && file_exists($scanPath)) {
                @unlink($scanPath);
            }

            // 更新文件记录（不隔离文件，仅记录扫描结果）
            $file->forceFill([
                'malware_scan_passed' => !$scanResult['is_malicious'],
                'malware_scan_checked_at' => now(),
                'malware_scan_details' => json_encode($scanResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
                'risk_score' => $risk['score'],
                'risk_reasons_json' => $risk['reasons'],
            ])->save();

            // 注意：不再自动隔离恶意代码文件，也不封禁IP
            // 扫描结果会在分享页面显示，供用户参考

        } catch (\Exception $e) {
            // 扫描出错时按风险文件处理，保持下载与预览拦截
            $file->forceFill([
                'malware_scan_passed' => false,
                'malware_scan_checked_at' => now(),
                'risk_score' => max((int) ($file->risk_score ?? 0), 95),
                'risk_reasons_json' => array_values(array_unique(array_merge(
                    is_array($file->risk_reasons_json) ? $file->risk_reasons_json : [],
                    ['内容安全扫描异常，已按高风险处理']
                ))),
                'malware_scan_details' => json_encode([
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'fail_closed' => true,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)
            ])->save();
        }
    }

    private function mark(SharedFile $file, string $status, string $result): void
    {
        $file->forceFill([
            'scan_status' => $status,
            'scan_result' => $result,
            'scan_checked_at' => now(),
        ])->save();
    }

    private function malwareRisk(SharedFile $file, array $scanResult): array
    {
        $currentScore = (int) ($file->risk_score ?? 0);
        $currentReasons = is_array($file->risk_reasons_json) ? $file->risk_reasons_json : [];

        if (!($scanResult['is_malicious'] ?? false)) {
            return ['score' => $currentScore, 'reasons' => $currentReasons];
        }

        $threats = collect($scanResult['threat_types'] ?? [])
            ->map(fn ($threat): string => trim((string) $threat))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'score' => max($currentScore, 95),
            'reasons' => array_values(array_unique(array_merge($currentReasons, [
                '内容安全扫描检测到风险',
            ], $threats))),
        ];
    }
}
