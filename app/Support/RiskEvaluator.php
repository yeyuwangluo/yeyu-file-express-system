<?php

namespace App\Support;

use App\Models\BlockedIp;
use App\Models\FileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RiskEvaluator
{
    /**
     * @return array{score:int,reasons:array<int,string>,blocked:bool}
     */
    public function evaluateUpload(Request $request, string $originalName, ?string $extension, ?string $mimeType, int $size): array
    {
        $score = 0;
        $reasons = [];
        $ip = $request->ip();
        $extension = strtolower((string) $extension);
        $mimeType = strtolower((string) $mimeType);

        if (BlockedIp::matches($ip, 'upload')) {
            $score = 100;
            $reasons[] = 'ip_blocked';
        }

        if ($extension !== '' && in_array($extension, $this->highRiskExtensions(), true)) {
            $score += 45;
            $reasons[] = 'high_risk_extension';
        }

        if ($this->hasSuspiciousDoubleExtension($originalName)) {
            $score += 30;
            $reasons[] = 'suspicious_double_extension';
        }

        if ($extension !== '' && $mimeType !== '' && ! $this->mimeMatchesExtension($extension, $mimeType)) {
            $score += 20;
            $reasons[] = 'mime_extension_mismatch';
        }

        $uploadLimit = max(1, (int) YeyuFileExpressSettings::upload()['maxFileSize']);
        if ($size >= (int) floor($uploadLimit * 0.9)) {
            $score += 10;
            $reasons[] = 'near_size_limit';
        }

        if (Str::contains($originalName, ['..', "\0"])) {
            $score += 30;
            $reasons[] = 'suspicious_name';
        }

        $window = max(1, (int) config('yeyu_file_express.risk.repeated_failure_window_minutes', 60));
        $limit = max(1, (int) config('yeyu_file_express.risk.repeated_failure_limit', 5));
        $failedUploads = FileUpload::query()
            ->where('ip', $ip)
            ->where('success', false)
            ->where('created_at', '>=', now()->subMinutes($window))
            ->count();

        if ($failedUploads >= $limit) {
            $score += 35;
            $reasons[] = 'repeated_failed_uploads';
        }

        $recentSuccessfulUploads = FileUpload::query()
            ->where('ip', $ip)
            ->where('success', true)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();
        if ($recentSuccessfulUploads >= 20) {
            $score += 25;
            $reasons[] = 'high_frequency_uploads';
        }

        $recentUploadBytes = FileUpload::query()
            ->where('ip', $ip)
            ->where('success', true)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->sum('size');
        if ($recentUploadBytes >= $uploadLimit * 3) {
            $score += 20;
            $reasons[] = 'high_volume_uploads';
        }

        $score = min(100, $score);

        return [
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
            'blocked' => $score >= (int) YeyuFileExpressSettings::risk()['blockScore'],
        ];
    }

    /**
     * @return array<int,string>
     */
    private function highRiskExtensions(): array
    {
        return ['bat', 'cmd', 'com', 'cpl', 'dll', 'exe', 'hta', 'jar', 'js', 'jse', 'lnk', 'msi', 'ps1', 'scr', 'sh', 'vbe', 'vbs', 'wsf'];
    }

    private function hasSuspiciousDoubleExtension(string $name): bool
    {
        $parts = array_values(array_filter(explode('.', strtolower($name))));
        if (count($parts) < 3) {
            return false;
        }

        $last = $parts[count($parts) - 1];
        $previous = $parts[count($parts) - 2];

        return in_array($last, $this->highRiskExtensions(), true)
            && in_array($previous, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx'], true);
    }

    private function mimeMatchesExtension(string $extension, string $mimeType): bool
    {
        $families = [
            'jpg' => ['image/'],
            'jpeg' => ['image/'],
            'png' => ['image/'],
            'gif' => ['image/'],
            'webp' => ['image/'],
            'avif' => ['image/'],
            'bmp' => ['image/'],
            'mp3' => ['audio/'],
            'wav' => ['audio/'],
            'flac' => ['audio/'],
            'm4a' => ['audio/'],
            'aac' => ['audio/'],
            'ogg' => ['audio/', 'video/'],
            'mp4' => ['video/'],
            'webm' => ['video/'],
            'mov' => ['video/', 'application/quicktime'],
            'pdf' => ['application/pdf'],
            'txt' => ['text/plain'],
            'md' => ['text/', 'application/octet-stream'],
            'json' => ['application/json', 'text/'],
            'csv' => ['text/csv', 'application/csv', 'text/plain'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
            'rar' => ['application/vnd.rar', 'application/x-rar', 'application/octet-stream'],
            '7z' => ['application/x-7z-compressed', 'application/octet-stream'],
            'apk' => ['application/vnd.android.package-archive', 'application/zip', 'application/octet-stream'],
        ];

        if (! isset($families[$extension])) {
            return true;
        }

        foreach ($families[$extension] as $allowed) {
            if (Str::startsWith($mimeType, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
