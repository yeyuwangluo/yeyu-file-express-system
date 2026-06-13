<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AIScanLog;
use App\Models\SharedFile;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class SharePageController extends Controller
{
    public function myFiles(): View
    {
        return view('files.my-files');
    }

    public function show(string $code): View
    {
        $normalized = strtoupper($code);
        $file = SharedFile::query()->where('code', $normalized)->first();

        $malwareScanDetails = null;
        $hasMalwareThreats = false;
        $scanPending = false;
        $scanFailed = false;
        $scanPassed = false;
        
        if ($file && $file->malware_scan_checked_at) {
            try {
                $fullDetails = is_array($file->malware_scan_details)
                    ? $file->malware_scan_details
                    : json_decode((string) $file->malware_scan_details, true);

                if (is_array($fullDetails)) {
                    $malwareScanDetails = [
                        'is_malicious' => $fullDetails['is_malicious'] ?? false,
                        'threats' => array_unique($fullDetails['threats'] ?? []),
                        'is_archive' => $fullDetails['is_archive'] ?? false,
                        'details' => $fullDetails['details'] ?? null,
                    ];
                    $hasMalwareThreats = !$file->malware_scan_passed;
                    $scanPassed = $file->malware_scan_passed;
                }
            } catch (\Exception $e) {
                $malwareScanDetails = null;
                $scanFailed = true;
            }
        } elseif ($file && !$file->malware_scan_checked_at) {
            $scanPending = true;
        }

        return view('files.show', [
            'code' => $normalized,
            'file' => $file,
            'expired' => !$file || $file->status !== 'active',
            'malwareScanDetails' => $malwareScanDetails,
            'hasMalwareThreats' => $hasMalwareThreats,
            'scanPending' => $scanPending,
            'scanFailed' => $scanFailed,
            'scanPassed' => $scanPassed,
            'notices' => $file ? $this->noticesForCode($file->code) : [],
            'appeals' => $file ? $this->appealsForCode($file->code) : [],
            'fileMeta' => $file ? $this->fileMetaForCode($file->code) : [],
        ]);
    }

    public function showBatch(string $token): View
    {
        $setting = Setting::query()->where('group', 'batch_share')->where('key', $token)->latest('updated_at')->first();
        $decoded = $setting ? (is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true)) : null;
        $expired = ! is_array($decoded) || ! empty($decoded['closed_at']) || strtotime((string) ($decoded['expires_at'] ?? '')) <= time();
        $codes = is_array($decoded['codes'] ?? null) ? array_slice($decoded['codes'], 0, 50) : [];
        $files = $expired ? collect() : SharedFile::query()->whereIn('code', $codes)->get()
            ->filter(fn (SharedFile $file): bool => $file->status === 'active' && ! $file->isExpired())
            ->sortBy(fn (SharedFile $file): int => array_search($file->code, $codes, true) ?: 0)
            ->values();
        $downloadSummary = $expired ? ['downloadable' => 0, 'skipped' => []] : $this->batchDownloadSummary($codes);

        return view('files.batch-share', [
            'token' => $token,
            'batch' => is_array($decoded) ? $decoded : [],
            'expired' => $expired,
            'files' => $files,
            'downloadSummary' => $downloadSummary,
        ]);
    }

    public function downloadBatch(string $token): BinaryFileResponse
    {
        $setting = Setting::query()->where('group', 'batch_share')->where('key', $token)->latest('updated_at')->first();
        $decoded = $setting ? (is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true)) : null;
        abort_unless(is_array($decoded) && empty($decoded['closed_at']) && strtotime((string) ($decoded['expires_at'] ?? '')) > time(), 404);

        $codes = is_array($decoded['codes'] ?? null) ? array_slice($decoded['codes'], 0, 50) : [];
        $files = SharedFile::query()->whereIn('code', $codes)->get()
            ->filter(fn (SharedFile $file): bool => $file->status === 'active'
                && ! $file->isExpired()
                && (bool) $file->malware_scan_checked_at
                && (bool) $file->malware_scan_passed
                && Storage::disk($file->disk)->exists($file->path))
            ->values();
        abort_if($files->isEmpty(), 404);

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
        $zipPath = $tmpDir.'/batch-'.$token.'-'.Str::random(8).'.zip';
        $zip = new ZipArchive();
        abort_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 500);

        $usedNames = [];
        $added = 0;
        foreach ($files as $file) {
            $name = str_replace(["\\", '/', "\r", "\n"], '_', (string) $file->original_name) ?: $file->code;
            $base = $name;
            $index = 1;
            while (isset($usedNames[$name])) {
                $extension = pathinfo($base, PATHINFO_EXTENSION);
                $name = pathinfo($base, PATHINFO_FILENAME).'_'.$index.($extension !== '' ? '.'.$extension : '');
                $index++;
            }
            $usedNames[$name] = true;
            try {
                $stream = Storage::disk($file->disk)->readStream($file->path);
                if (is_resource($stream)) {
                    $zip->addFromString($name, stream_get_contents($stream));
                    fclose($stream);
                    $added++;
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        $zip->close();
        abort_if($added === 0, 404);

        $filename = 'batch-'.$token.'.zip';
        return response()->download($zipPath, $filename, ['Cache-Control' => 'no-store'])->deleteFileAfterSend(true);
    }

    private function batchDownloadSummary(array $codes): array
    {
        $files = SharedFile::query()->whereIn('code', $codes)->get()->keyBy('code');
        $downloadable = 0;
        $skipped = [];
        foreach ($codes as $code) {
            $file = $files->get($code);
            $reason = null;
            if (! $file) {
                $reason = '文件不存在';
            } elseif ($file->status !== 'active') {
                $reason = '分享已关闭';
            } elseif ($file->isExpired()) {
                $reason = '文件已过期';
            } elseif (! $file->malware_scan_checked_at || ! $file->malware_scan_passed) {
                $reason = '安全扫描未通过';
            } elseif ($file->disk !== 'local') {
                $reason = '外部存储暂不支持打包';
            } elseif (! Storage::disk($file->disk)->exists($file->path)) {
                $reason = '源文件不可读取';
            }
            if ($reason) {
                $skipped[] = ['code' => $code, 'name' => $file?->original_name ?: $code, 'reason' => $reason];
            } else {
                $downloadable++;
            }
        }

        return ['downloadable' => $downloadable, 'skipped' => $skipped];
    }

    public function showThreatDetails(string $code)
    {
        $normalized = strtoupper($code);
        $file = SharedFile::query()->where('code', $normalized)->first();

        if (!$file || !$file->malware_scan_checked_at || $file->malware_scan_passed) {
            return redirect()->route('files.show', ['code' => $code]);
        }

        try {
            $malwareScanDetails = is_array($file->malware_scan_details)
                ? $file->malware_scan_details
                : json_decode((string) $file->malware_scan_details, true);

            if (! is_array($malwareScanDetails)) {
                $malwareScanDetails = null;
            }
        } catch (\Exception $e) {
            $malwareScanDetails = null;
        }

        return view('files.threat-details', [
            'code' => $normalized,
            'file' => $file,
            'malwareScanDetails' => $malwareScanDetails,
            'aiScanLogs' => AIScanLog::query()
                ->where('file_id', $file->id)
                ->where('skipped', false)
                ->latest()
                ->get(['filename', 'threat_type', 'confidence', 'reason', 'model', 'scanner', 'created_at'])
                ->values(),
            'appeals' => $this->appealsForCode($file->code),
        ]);
    }

    public function submitAppeal(Request $request, string $code): RedirectResponse
    {
        $normalized = strtoupper($code);
        $file = SharedFile::query()->where('code', $normalized)->first();

        if (! $file || ! $file->malware_scan_checked_at || $file->malware_scan_passed) {
            return redirect()->route('files.show', ['code' => $normalized]);
        }

        $rateKey = 'risk-appeal:'.$normalized.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            return back()->with('appeal_error', '提交过于频繁，请稍后再试。');
        }
        RateLimiter::hit($rateKey, 3600);

        $data = $request->validate([
            'contact' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        $lookupCode = strtoupper(Str::random(10));
        Setting::query()->create([
            'group' => 'risk_appeal',
            'key' => $file->id.':'.now()->format('YmdHis').':'.substr(hash('sha256', $request->ip().'|'.$data['reason']), 0, 10),
            'value' => json_encode([
                'file_id' => $file->id,
                'code' => $file->code,
                'lookup_code' => $lookupCode,
                'contact' => trim((string) ($data['contact'] ?? '')),
                'reason' => trim((string) $data['reason']),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'status' => 'pending',
                'submitted_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'type' => 'json',
        ]);

        return back()->with('appeal_status', '申诉已提交，查询码：'.$lookupCode.'，查询地址：'.route('files.appeal-status', ['lookupCode' => $lookupCode], false));
    }

    public function appealStatus(string $lookupCode): View
    {
        $normalized = strtoupper($lookupCode);
        $appeal = Setting::query()
            ->where('group', 'risk_appeal')
            ->latest('updated_at')
            ->limit(500)
            ->get(['key', 'value', 'updated_at'])
            ->map(function (Setting $setting): array {
                $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);
                if (! is_array($decoded)) {
                    return [];
                }

                return $decoded + ['key' => $setting->key, 'updated_at' => optional($setting->updated_at)->toDateTimeString()];
            })
            ->first(fn (array $item): bool => strtoupper((string) ($item['lookup_code'] ?? '')) === $normalized) ?: [];

        return view('files.appeal-status', [
            'lookupCode' => $normalized,
            'appeal' => $appeal,
        ]);
    }

    private function noticesForCode(string $code): array
    {
        return Setting::query()
            ->where('group', 'user_notice')
            ->where('key', 'like', strtoupper($code).':%')
            ->latest('updated_at')
            ->limit(5)
            ->get(['value'])
            ->map(function (Setting $setting): array {
                $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);

                return is_array($decoded) ? $decoded : [];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function appealsForCode(string $code): array
    {
        return Setting::query()
            ->where('group', 'risk_appeal')
            ->latest('updated_at')
            ->limit(200)
            ->get(['key', 'value'])
            ->map(function (Setting $setting): array {
                $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);
                if (! is_array($decoded)) {
                    return [];
                }

                return $decoded + ['key' => $setting->key];
            })
            ->filter(fn (array $appeal): bool => strtoupper((string) ($appeal['code'] ?? '')) === strtoupper($code))
            ->take(5)
            ->values()
            ->all();
    }

    private function fileMetaForCode(string $code): array
    {
        $setting = Setting::query()->where('group', 'file_meta')->where('key', strtoupper($code))->latest('updated_at')->first();
        if (! $setting) {
            return [];
        }

        $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
