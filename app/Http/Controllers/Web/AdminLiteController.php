<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\DeleteStoredFile;
use App\Jobs\ScanUploadedFile;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\FileDownload;
use App\Models\FileUpload;
use App\Models\HealthCheck;
use App\Models\AIScanLog;
use App\Models\RiskDownloadAckLog;
use App\Models\SharedFile;
use App\Models\Setting;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\YeyuFileExpressSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Throwable;

class AdminLiteController extends Controller
{
    public function dashboard(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'code' => trim((string) $request->query('code', '')),
            'filename' => trim((string) $request->query('filename', '')),
            'ip' => trim((string) $request->query('ip', '')),
            'status' => (string) $request->query('status', ''),
            'expires' => (string) $request->query('expires', ''),
            'risk' => (string) $request->query('risk', ''),
            'archive_scan' => (string) $request->query('archive_scan', ''),
            'threat_type' => trim((string) $request->query('threat_type', '')),
            'risk_ip' => trim((string) $request->query('risk_ip', '')),
            'risk_ext' => trim((string) $request->query('risk_ext', '')),
            'risk_hash' => trim((string) $request->query('risk_hash', '')),
            'risk_media' => (string) $request->query('risk_media', ''),
            'risk_min_score' => trim((string) $request->query('risk_min_score', '')),
            'uploaded_from' => trim((string) $request->query('uploaded_from', '')),
            'uploaded_to' => trim((string) $request->query('uploaded_to', '')),
            'size_min' => trim((string) $request->query('size_min', '')),
            'size_max' => trim((string) $request->query('size_max', '')),
            'downloads_min' => trim((string) $request->query('downloads_min', '')),
            'review' => (string) $request->query('review', ''),
            'failed_actionable' => (string) $request->query('failed_actionable', ''),
            'audit_action' => trim((string) $request->query('audit_action', '')),
            'audit_target' => trim((string) $request->query('audit_target', '')),
            'audit_ip' => trim((string) $request->query('audit_ip', '')),
        ];
        $riskReviewStates = $this->riskReviewStates();
        $riskAppeals = $this->riskAppeals();
        $recentRiskFiles = SharedFile::query()->where('malware_scan_passed', false)->latest('malware_scan_checked_at')->limit(50)->get();
        if ($filters['threat_type'] !== '') {
            $recentRiskFiles = $recentRiskFiles
                ->filter(fn (SharedFile $file): bool => $this->fileHasThreatType($file, $filters['threat_type']))
                ->values();
        }
        if ($filters['risk_ip'] !== '') {
            $recentRiskFiles = $recentRiskFiles
                ->filter(fn (SharedFile $file): bool => (string) $file->uploader_ip === $filters['risk_ip'])
                ->values();
        }
        if ($filters['risk_ext'] !== '') {
            $recentRiskFiles = $recentRiskFiles
                ->filter(fn (SharedFile $file): bool => strtolower((string) ($file->extension ?: pathinfo((string) $file->original_name, PATHINFO_EXTENSION))) === strtolower($filters['risk_ext']))
                ->values();
        }
        if ($filters['risk_hash'] !== '') {
            $recentRiskFiles = $recentRiskFiles
                ->filter(fn (SharedFile $file): bool => (string) ($file->sha256 ?? '') === $filters['risk_hash'])
                ->values();
        }
        if (in_array($filters['risk_media'], ['image', 'video'], true)) {
            $recentRiskFiles = $recentRiskFiles
                ->filter(fn (SharedFile $file): bool => str_starts_with(strtolower((string) $file->mime_type), $filters['risk_media'].'/'))
                ->values();
        }
        if (ctype_digit($filters['risk_min_score'])) {
            $recentRiskFiles = $recentRiskFiles
                ->filter(fn (SharedFile $file): bool => (int) ($file->risk_score ?? 0) >= (int) $filters['risk_min_score'])
                ->values();
        }
        if (in_array($filters['review'], ['pending', 'confirmed', 'false_positive', 'rescanned'], true)) {
            $recentRiskFiles = $recentRiskFiles
                ->filter(fn (SharedFile $file): bool => ($riskReviewStates[$file->id]['status'] ?? 'pending') === $filters['review'])
                ->values();
        }
        if (in_array($filters['archive_scan'], ['partial', 'skipped', 'media', 'ai_failed'], true)) {
            $recentRiskFiles = $recentRiskFiles
                ->filter(fn (SharedFile $file): bool => $this->fileMatchesArchiveScanFilter($file, $filters['archive_scan']))
                ->values();
        }

        $files = SharedFile::query()
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $query) use ($filters): void {
                    $query->where('original_name', 'like', '%'.$filters['q'].'%')
                        ->orWhere('code', 'like', '%'.$filters['q'].'%');
                });
            })
            ->when($filters['code'] !== '', fn (Builder $query) => $query->where('code', 'like', '%'.$filters['code'].'%'))
            ->when($filters['filename'] !== '', fn (Builder $query) => $query->where('original_name', 'like', '%'.$filters['filename'].'%'))
            ->when($filters['ip'] !== '', fn (Builder $query) => $query->where('uploader_ip', 'like', '%'.$filters['ip'].'%'))
            ->when(in_array($filters['status'], ['active', 'expired', 'deleted', 'blocked'], true), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['risk'] === 'threat', fn (Builder $query) => $query->where('malware_scan_passed', false)->whereNotNull('malware_scan_checked_at'))
            ->when($filters['risk'] === 'high', fn (Builder $query) => $query->where('risk_score', '>=', 80))
            ->when($filters['risk'] === 'pending', fn (Builder $query) => $query->whereNull('malware_scan_checked_at'))
            ->when($filters['archive_scan'] === 'partial', fn (Builder $query) => $query->where('malware_scan_details', 'like', '%"archive_scan"%')->where('malware_scan_details', 'not like', '%"coverage_percent":100%'))
            ->when($filters['archive_scan'] === 'skipped', fn (Builder $query) => $query->where('malware_scan_details', 'like', '%"archive_scan"%')->where('malware_scan_details', 'not like', '%"skipped_files":0%'))
            ->when($filters['archive_scan'] === 'media', fn (Builder $query) => $query->where('malware_scan_details', 'like', '%"entry_type":"media"%'))
            ->when($filters['archive_scan'] === 'ai_failed', fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->where('malware_scan_details', 'like', '%media_review_failed%')
                    ->orWhere('malware_scan_details', 'like', '%media_review_required%');
            }))
            ->when($filters['threat_type'] !== '', fn (Builder $query) => $query->where('malware_scan_details', 'like', '%'.$filters['threat_type'].'%'))
            ->when($filters['uploaded_from'] !== '', fn (Builder $query) => $query->where('created_at', '>=', $filters['uploaded_from'].' 00:00:00'))
            ->when($filters['uploaded_to'] !== '', fn (Builder $query) => $query->where('created_at', '<=', $filters['uploaded_to'].' 23:59:59'))
            ->when(ctype_digit($filters['size_min']), fn (Builder $query) => $query->where('size', '>=', (int) $filters['size_min'] * 1024))
            ->when(ctype_digit($filters['size_max']), fn (Builder $query) => $query->where('size', '<=', (int) $filters['size_max'] * 1024))
            ->when(ctype_digit($filters['downloads_min']), fn (Builder $query) => $query->where('download_count', '>=', (int) $filters['downloads_min']))
            ->when($filters['expires'] === 'active', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
            })
            ->when($filters['expires'] === 'expired', function (Builder $query): void {
                $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
            })
            ->when($filters['expires'] === 'today', function (Builder $query): void {
                $query->whereBetween('expires_at', [now()->startOfDay(), now()->endOfDay()]);
            })
            ->when($filters['expires'] === 'week', function (Builder $query): void {
                $query->whereBetween('expires_at', [now(), now()->addWeek()]);
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.dashboard', [
            'stats' => [
                'todayUploads' => FileUpload::query()->whereDate('created_at', today())->where('success', true)->count(),
                'todayDownloads' => FileDownload::query()->whereDate('created_at', today())->where('success', true)->count(),
                'todayUploadBytes' => FileUpload::query()->whereDate('created_at', today())->where('success', true)->sum('size'),
                'activeFiles' => SharedFile::query()->where('status', 'active')->where(function ($query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })->count(),
                'expiredFiles' => SharedFile::query()->where(function ($query): void {
                    $query->where('status', 'expired')->orWhere('expires_at', '<=', now());
                })->count(),
                'storageBytes' => SharedFile::query()->sum('size'),
            ],
            'trends' => $this->sevenDayTrends(),
            'files' => $files,
            'filters' => $filters,
            'settings' => YeyuFileExpressSettings::configPayload() + ['appDownload' => YeyuFileExpressSettings::appDownload()],
            'netdisk123' => YeyuFileExpressSettings::netdisk123(),
            'ai_scan_enabled' => Setting::valueFor('ai_scan', 'ai_scan_enabled', false),
            'ai_scan_api_url' => Setting::valueFor('ai_scan', 'ai_scan_api_url', ''),
            'ai_scan_api_key' => '',
            'ai_scan_model' => Setting::valueFor('ai_scan', 'ai_scan_model', 'gpt-4'),
            'ai_scan_timeout' => Setting::valueFor('ai_scan', 'ai_scan_timeout', 30),
            'ai_scan_max_file_size' => Setting::valueFor('ai_scan', 'ai_scan_max_file_size', 102400),
            'ai_scan_retry_count' => Setting::valueFor('ai_scan', 'ai_scan_retry_count', 2),
            'archive_max_scan_files' => Setting::valueFor('ai_scan', 'archive_max_scan_files', 30),
            'archive_scan_extensions' => Setting::valueFor('ai_scan', 'archive_scan_extensions', 'php,phtml,phar,js,mjs,ts,jsx,tsx,py,java,c,cpp,sh,bash,zsh,bat,cmd,ps1,rb,pl,go,rs,lua,asp,aspx,jsp,txt,md,json,xml,yaml,yml,ini,env,htaccess'),
            'archive_media_scan_enabled' => Setting::valueFor('ai_scan', 'archive_media_scan_enabled', true),
            'archive_media_extensions' => Setting::valueFor('ai_scan', 'archive_media_extensions', 'jpg,jpeg,jpe,jfif,pjpeg,pjp,png,gif,webp,bmp,dib,avif,heic,heif,tif,tiff,ico'),
            'archive_media_max_file_size' => Setting::valueFor('ai_scan', 'archive_media_max_file_size', 8192),
            'archive_media_failure_policy' => Setting::valueFor('ai_scan', 'archive_media_failure_policy', 'block'),
            'riskFalsePositivePolicy' => Setting::valueFor('risk', 'false_positive_policy', 'require_ack'),
            'opsAlert' => $this->opsAlertSettings(),
            'announcements' => Announcement::query()->latest()->limit(20)->get(),
            'blockedIps' => BlockedIp::query()
                ->where(function (Builder $query): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest()
                ->limit(20)
                ->get(),
            'uploads' => FileUpload::query()->latest('created_at')->limit(20)->get(),
            'downloads' => FileDownload::query()->latest('created_at')->limit(20)->get(),
            'riskDownloads' => Schema::hasTable('risk_download_ack_logs')
                ? RiskDownloadAckLog::query()->latest('created_at')->limit(20)->get()
                : FileDownload::query()->where('failure_reason', 'risk_ack_confirmed')->latest('created_at')->limit(20)->get(),
            'recentRiskFiles' => $recentRiskFiles->take(20),
            'batchShares' => $this->batchShares(),
            'aiFailureLogs' => AIScanLog::query()->where('skipped', true)->latest('created_at')->limit(20)->get(),
            'auditLogs' => $this->auditLogs($filters),
            'errorLogs' => AuditLog::query()->where('action', 'like', '%.failed')->latest('created_at')->limit(10)->get(),
            'healthChecks' => HealthCheck::query()->latest('checked_at')->limit(10)->get(),
            'operationsHealth' => $this->operationsHealth(),
            'opsCheckHistory' => $this->opsCheckHistory(),
            'failedJobs' => $this->failedJobs($filters['failed_actionable'] === '1'),
            'suspiciousDownloads' => $this->suspiciousDownloads(),
            'recentLoginAuditLogs' => AuditLog::query()->whereIn('action', ['admin.login.success', 'admin.login.failed', 'admin.logout'])->latest('created_at')->limit(20)->get(),
            'configAuditLogs' => AuditLog::query()->whereIn('action', ['settings.update', 'ai_settings.update'])->latest('created_at')->limit(20)->get(),
            'adminUsers' => User::query()->where('is_admin', true)->orderBy('email')->limit(50)->get(),
            'canManageAdmins' => $this->canManageAdmins($request),
            'adminPermissions' => $this->adminPermissionFlags($request),
            'adminPermissionLabels' => $this->adminPermissionLabels(),
            'currentAdminSummary' => $this->currentAdminSummary($request),
            'riskReviewStates' => $riskReviewStates,
            'riskAppeals' => $riskAppeals,
            'riskReviewStats' => $this->riskReviewStats($riskReviewStates),
            'riskInsights' => $this->riskInsights($riskReviewStates, $riskAppeals),
            'downloadRiskEvents' => $this->downloadRiskEvents(),
            'userNotices' => $this->userNotices(),
            'termsContent' => Setting::valueFor('content', 'terms') ?? self::defaultTermsContent(),
            'privacyContent' => Setting::valueFor('content', 'privacy') ?? self::defaultPrivacyContent(),
        ]);
    }


    public function aiSettings(): View
    {
        return view('admin.ai-settings', [
            'ai_scan_enabled' => Setting::valueFor('ai_scan', 'ai_scan_enabled', false),
            'ai_scan_api_url' => Setting::valueFor('ai_scan', 'ai_scan_api_url', ''),
            'ai_scan_api_key' => '',
            'ai_scan_model' => Setting::valueFor('ai_scan', 'ai_scan_model', 'gpt-4'),
            'ai_scan_timeout' => Setting::valueFor('ai_scan', 'ai_scan_timeout', 30),
            'ai_scan_max_file_size' => Setting::valueFor('ai_scan', 'ai_scan_max_file_size', 102400),
            'ai_scan_retry_count' => Setting::valueFor('ai_scan', 'ai_scan_retry_count', 2),
            'archive_max_scan_files' => Setting::valueFor('ai_scan', 'archive_max_scan_files', 30),
            'archive_scan_extensions' => Setting::valueFor('ai_scan', 'archive_scan_extensions', 'php,phtml,phar,js,mjs,ts,jsx,tsx,py,java,c,cpp,sh,bash,zsh,bat,cmd,ps1,rb,pl,go,rs,lua,asp,aspx,jsp,txt,md,json,xml,yaml,yml,ini,env,htaccess'),
            'archive_media_scan_enabled' => Setting::valueFor('ai_scan', 'archive_media_scan_enabled', true),
            'archive_media_extensions' => Setting::valueFor('ai_scan', 'archive_media_extensions', 'jpg,jpeg,jpe,jfif,pjpeg,pjp,png,gif,webp,bmp,dib,avif,heic,heif,tif,tiff,ico'),
            'archive_media_max_file_size' => Setting::valueFor('ai_scan', 'archive_media_max_file_size', 8192),
            'archive_media_failure_policy' => Setting::valueFor('ai_scan', 'archive_media_failure_policy', 'block'),
        ]);
    }

    public function updateAiSettings(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'ai.update');

        $validated = $request->validate([
            'ai_scan_enabled' => 'sometimes|accepted',
            'ai_scan_api_url' => 'nullable|string|max:500',
            'ai_scan_api_key' => 'nullable|string|max:200',
            'ai_scan_model' => 'nullable|string|max:100',
            'ai_scan_timeout' => 'nullable|integer|min:5|max:120',
            'ai_scan_max_file_size' => 'nullable|integer|min:1|max:1048576',
            'ai_scan_retry_count' => 'nullable|integer|min:1|max:5',
            'archive_max_scan_files' => 'nullable|integer|min:1|max:100',
            'archive_scan_extensions' => 'nullable|string|max:1000',
            'archive_media_scan_enabled' => 'sometimes|accepted',
            'archive_media_extensions' => 'nullable|string|max:500',
            'archive_media_max_file_size' => 'nullable|integer|min:1|max:8192',
            'archive_media_failure_policy' => 'nullable|in:block,review,allow',
        ]);

        Setting::setValue('ai_scan', 'ai_scan_enabled', isset($validated['ai_scan_enabled']));
        Setting::setValue('ai_scan', 'ai_scan_api_url', $validated['ai_scan_api_url'] ?? '');
        if (array_key_exists('ai_scan_api_key', $validated) && trim((string) $validated['ai_scan_api_key']) !== '') {
            Setting::setValue('ai_scan', 'ai_scan_api_key', $validated['ai_scan_api_key']);
        }
        Setting::setValue('ai_scan', 'ai_scan_model', $validated['ai_scan_model'] ?? 'gpt-4');
        Setting::setValue('ai_scan', 'ai_scan_timeout', $validated['ai_scan_timeout'] ?? 30);
        Setting::setValue('ai_scan', 'ai_scan_max_file_size', $validated['ai_scan_max_file_size'] ?? 102400);
        Setting::setValue('ai_scan', 'ai_scan_retry_count', $validated['ai_scan_retry_count'] ?? 2);
        Setting::setValue('ai_scan', 'archive_max_scan_files', $validated['archive_max_scan_files'] ?? 30);
        Setting::setValue('ai_scan', 'archive_scan_extensions', $validated['archive_scan_extensions'] ?? 'php,phtml,phar,js,mjs,ts,jsx,tsx,py,java,c,cpp,sh,bash,zsh,bat,cmd,ps1,rb,pl,go,rs,lua,asp,aspx,jsp,txt,md,json,xml,yaml,yml,ini,env,htaccess');
        Setting::setValue('ai_scan', 'archive_media_scan_enabled', isset($validated['archive_media_scan_enabled']));
        Setting::setValue('ai_scan', 'archive_media_extensions', $validated['archive_media_extensions'] ?? 'jpg,jpeg,jpe,jfif,pjpeg,pjp,png,gif,webp,bmp,dib,avif,heic,heif,tif,tiff,ico');
        Setting::setValue('ai_scan', 'archive_media_max_file_size', $validated['archive_media_max_file_size'] ?? 8192);
        Setting::setValue('ai_scan', 'archive_media_failure_policy', $validated['archive_media_failure_policy'] ?? 'block');

        AuditLogger::write($request, 'ai_settings.update', 'settings', null, [
            'enabled' => isset($validated['ai_scan_enabled']),
            'model' => $validated['ai_scan_model'] ?? 'gpt-4',
            'timeout' => $validated['ai_scan_timeout'] ?? 30,
            'max_file_size' => $validated['ai_scan_max_file_size'] ?? 102400,
            'retry_count' => $validated['ai_scan_retry_count'] ?? 2,
            'archive_media_scan_enabled' => isset($validated['archive_media_scan_enabled']),
            'archive_media_extensions' => $validated['archive_media_extensions'] ?? 'jpg,jpeg,jpe,jfif,pjpeg,pjp,png,gif,webp,bmp,dib,avif,heic,heif,tif,tiff,ico',
            'archive_media_max_file_size' => $validated['archive_media_max_file_size'] ?? 8192,
            'archive_media_failure_policy' => $validated['archive_media_failure_policy'] ?? 'block',
            'api_key_changed' => array_key_exists('ai_scan_api_key', $validated) && trim((string) $validated['ai_scan_api_key']) !== '',
        ]);

        return redirect()->back()->with('status', 'AI配置已更新');
    }


    public function testAiConnection(Request $request): JsonResponse
    {
        $this->ensureCan($request, 'ai.update');

        $validated = $request->validate([
            'api_url' => 'required|string|max:500',
            'api_key' => 'required|string|max:200',
            'model' => 'required|string|max:100',
            'timeout' => 'nullable|integer|min:5|max:120',
        ]);

        try {
            $apiUrl = $validated['api_url'];
            $apiKey = $validated['api_key'];
            $model = $validated['model'];
            $timeout = $validated['timeout'] ?? 30;

            // Select API calling method based on API type
            if (str_contains($apiUrl, 'openai.com') || str_contains($apiUrl, 'api.openai')) {
                $result = $this->testOpenAIConnection($apiUrl, $apiKey, $model, $timeout);
            } elseif (str_contains($apiUrl, 'anthropic.com') || str_contains($apiUrl, 'api.anthropic')) {
                $result = $this->testClaudeConnection($apiUrl, $apiKey, $model, $timeout);
            } else {
                $result = $this->testCustomAPIConnection($apiUrl, $apiKey, $model, $timeout);
            }

            return response()->json([
                'success' => true,
                'message' => 'AI connection test successful',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'AI connection test failed: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }

    private function testOpenAIConnection(string $apiUrl, string $apiKey, string $model, int $timeout): array
    {
        try {
            $response = \Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($apiUrl, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => '你是一个测试助手。'],
                        ['role' => 'user', 'content' => '请回复"连接测试成功"'],
                    ],
                    'max_tokens' => 50,
                ]);

            if (!$response->successful()) {
                $errorMessage = 'API调用失败 (状态码: ' . $response->status() . ')';
                
                // 添加请求URL信息
                $errorMessage .= '\n请求URL: ' . $apiUrl;
                $errorMessage .= '\n使用模型: ' . $model;
                
                // 尝试解析错误响应
                $errorBody = $response->body();
                if (!empty($errorBody)) {
                    $errorMessage .= '\n错误详情: ' . substr($errorBody, 0, 500) . (strlen($errorBody) > 500 ? '...' : '');
                }
                
                // 添加常见错误的提示
                if ($response->status() === 404) {
                    $errorMessage .= '\n\nAPI地址不存在，请检查：';
                    $errorMessage .= '\n1. 确认API地址是否完整包含路径部分';
                    $errorMessage .= '\n2. OpenAI格式: https://api.openai.com/v1/chat/completions';
                    $errorMessage .= '\n3. 检查是否缺少 /v1/chat/completions 等路径';
                    $errorMessage .= '\n4. 确认API服务商的正确端点地址';
                } elseif ($response->status() === 401) {
                    $errorMessage .= '\n\nAPI密钥无效或已过期，请检查API密钥是否正确';
                } elseif ($response->status() === 429) {
                    $errorMessage .= '\n\nAPI请求频率超限，请稍后再试';
                } elseif ($response->status() === 500 || $response->status() === 502 || $response->status() === 503) {
                    $errorMessage .= '\n\nAPI服务暂时不可用，请稍后再试或联系API服务商';
                }
                
                throw new \Exception($errorMessage);
            }

            $content = $response->json('choices.0.message.content');
            $modelUsed = $response->json('model');
            $tokensUsed = $response->json('usage.total_tokens');

            return [
                'provider' => 'OpenAI',
                'model' => $modelUsed,
                'response' => $content,
                'tokens_used' => $tokensUsed,
                'response_time' => $response->handlerStats()['total_time'] ?? 'unknown'
            ];
        } catch (\Exception $e) {
            // 如果是网络连接错误
            if (strpos($e->getMessage(), 'cURL error') !== false) {
                throw new \Exception('网络连接失败: ' . $e->getMessage() . '\n\n请检查：\n1. 服务器网络连接是否正常\n2. API地址是否可以访问\n3. 防火墙是否阻止了请求');
            }
            throw $e;
        }
    }

    private function testClaudeConnection(string $apiUrl, string $apiKey, string $model, int $timeout): array
    {
        try {
            $response = \Http::timeout($timeout)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                    'anthropic-version' => '2023-06-01',
                ])
                ->post($apiUrl, [
                    'model' => $model,
                    'max_tokens' => 50,
                    'messages' => [
                        ['role' => 'user', 'content' => '请回复"连接测试成功"'],
                    ],
                ]);

            if (!$response->successful()) {
                $errorMessage = 'API调用失败 (状态码: ' . $response->status() . ')';
                
                // 添加请求URL信息
                $errorMessage .= '\n请求URL: ' . $apiUrl;
                $errorMessage .= '\n使用模型: ' . $model;
                
                // 尝试解析错误响应
                $errorBody = $response->body();
                if (!empty($errorBody)) {
                    $errorMessage .= '\n错误详情: ' . substr($errorBody, 0, 500) . (strlen($errorBody) > 500 ? '...' : '');
                }
                
                // 添加常见错误的提示
                if ($response->status() === 404) {
                    $errorMessage .= '\n\nAPI地址不存在，请检查：';
                    $errorMessage .= '\n1. 确认API地址是否完整包含路径部分';
                    $errorMessage .= '\n2. Claude格式: https://api.anthropic.com/v1/messages';
                    $errorMessage .= '\n3. 检查是否缺少 /v1/messages 路径';
                    $errorMessage .= '\n4. 确认API服务商的正确端点地址';
                } elseif ($response->status() === 401) {
                    $errorMessage .= '\n\nAPI密钥无效或已过期，请检查API密钥是否正确';
                } elseif ($response->status() === 429) {
                    $errorMessage .= '\n\nAPI请求频率超限，请稍后再试';
                } elseif ($response->status() === 500 || $response->status() === 502 || $response->status() === 503) {
                    $errorMessage .= '\n\nAPI服务暂时不可用，请稍后再试或联系API服务商';
                }
                
                throw new \Exception($errorMessage);
            }

            $content = $response->json('content.0.text');
            $tokensUsed = $response->json('usage.input_tokens') + $response->json('usage.output_tokens');

            return [
                'provider' => 'Claude',
                'model' => $model,
                'response' => $content,
                'tokens_used' => $tokensUsed,
                'response_time' => $response->handlerStats()['total_time'] ?? 'unknown'
            ];
        } catch (\Exception $e) {
            // 如果是网络连接错误
            if (strpos($e->getMessage(), 'cURL error') !== false) {
                throw new \Exception('网络连接失败: ' . $e->getMessage() . '\n\n请检查：\n1. 服务器网络连接是否正常\n2. API地址是否可以访问\n3. 防火墙是否阻止了请求');
            }
            throw $e;
        }
    }

    private function testCustomAPIConnection(string $apiUrl, string $apiKey, string $model, int $timeout): array
    {
        try {
            $response = \Http::timeout($timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($apiUrl, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => '你是一个测试助手。'],
                        ['role' => 'user', 'content' => '请回复"连接测试成功"'],
                    ],
                    'max_tokens' => 50,
                ]);

            if (!$response->successful()) {
                $errorMessage = 'API调用失败 (状态码: ' . $response->status() . ')';
                
                // 添加请求URL信息
                $errorMessage .= '\n请求URL: ' . $apiUrl;
                $errorMessage .= '\n使用模型: ' . $model;
                
                // 尝试解析错误响应
                $errorBody = $response->body();
                if (!empty($errorBody)) {
                    $errorMessage .= '\n错误详情: ' . substr($errorBody, 0, 500) . (strlen($errorBody) > 500 ? '...' : '');
                }
                
                // 添加常见错误的提示
                if ($response->status() === 404) {
                    $errorMessage .= '\n\nAPI地址不存在，请检查：';
                    $errorMessage .= '\n1. 确认API地址是否完整包含路径部分';
                    $errorMessage .= '\n2. OpenAI兼容格式: https://api.example.com/v1/chat/completions';
                    $errorMessage .= '\n3. 检查是否缺少路径部分（如 /v1/chat/completions）';
                    $errorMessage .= '\n4. 确认API服务商的正确端点地址';
                    $errorMessage .= '\n5. 查阅API服务商的官方文档';
                } elseif ($response->status() === 401) {
                    $errorMessage .= '\n\nAPI密钥无效或已过期，请检查API密钥是否正确';
                } elseif ($response->status() === 429) {
                    $errorMessage .= '\n\nAPI请求频率超限，请稍后再试';
                } elseif ($response->status() === 500 || $response->status() === 502 || $response->status() === 503) {
                    $errorMessage .= '\n\nAPI服务暂时不可用，请稍后再试或联系API服务商';
                }
                
                throw new \Exception($errorMessage);
            }

            // 尝试解析响应（支持OpenAI格式）
            $content = $response->json('choices.0.message.content') ?? $response->json('content.0.text') ?? '测试成功';
            $modelUsed = $response->json('model') ?? $model;
            $tokensUsed = $response->json('usage.total_tokens') ?? 0;

            return [
                'provider' => 'Custom',
                'model' => $modelUsed,
                'response' => $content,
                'tokens_used' => $tokensUsed,
                'response_time' => $response->handlerStats()['total_time'] ?? 'unknown'
            ];
        } catch (\Exception $e) {
            // 如果是网络连接错误
            if (strpos($e->getMessage(), 'cURL error') !== false) {
                throw new \Exception('网络连接失败: ' . $e->getMessage() . '\n\n请检查：\n1. 服务器网络连接是否正常\n2. API地址是否可以访问\n3. 防火墙是否阻止了请求');
            }
            throw $e;
        }
    }

    public function showLoginForm(): View
    {
        return view('admin.login');
    }

    public function processLogin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = 'admin-login:'.$request->ip();
        $emailThrottleKey = 'admin-login-email:'.sha1(strtolower($data['email']).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($throttleKey, 5) || RateLimiter::tooManyAttempts($emailThrottleKey, 5)) {
            return back()->withErrors(['email' => '登录尝试过多，请稍后再试'])->withInput($request->only('email'));
        }

        $admin = $this->resolveAdmin($data['email'], $data['password']);

        if (! $admin && ! $this->passwordMatches($data['email'], $data['password'])) {
            RateLimiter::hit($throttleKey, 300);
            RateLimiter::hit($emailThrottleKey, 300);
            AuditLogger::write($request, 'admin.login.failed', 'admin', null, ['email_hash' => sha1(strtolower($data['email']))]);

            return back()->withErrors(['email' => '邮箱或密码不正确'])->withInput($request->only('email'));
        }

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($emailThrottleKey);

        $request->session()->regenerate();
        $request->session()->put('admin_logged_in', true);
        $request->session()->put('admin_email', $data['email']);
        $request->session()->put('admin_role', $admin instanceof User ? ($admin->role ?: 'admin') : 'owner');
        if ($admin instanceof User) {
            $request->session()->put('admin_user_id', $admin->id);
            User::query()->where('id', $admin->id)->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);
        }
        AuditLogger::write($request, 'admin.login.success', $admin instanceof User ? User::class : 'admin', $admin instanceof User ? $admin->id : null, [
            'email_hash' => sha1(strtolower($data['email'])),
            'role' => $admin instanceof User ? ($admin->role ?: 'admin') : 'owner',
        ]);

        return redirect()->route('admin-lite.dashboard');
    }

    public function processLogout(Request $request): RedirectResponse
    {
        AuditLogger::write($request, 'admin.logout', 'admin', $request->session()->get('admin_user_id'), [
            'email_hash' => $request->session()->has('admin_email') ? sha1(strtolower((string) $request->session()->get('admin_email'))) : null,
        ]);
        $request->session()->forget(['admin_logged_in', 'admin_email', 'admin_role', 'admin_user_id']);

        return redirect()->route('admin-lite.login');
    }

    private function resolveAdmin(string $email, string $password): ?User
    {
        try {
            $admin = User::query()
                ->where('email', $email)
                ->where('is_admin', true)
                ->where('status', 'active')
                ->first();
        } catch (Throwable) {
            return null;
        }

        if (! $admin || ! Hash::check($password, $admin->password)) {
            return null;
        }

        return $admin;
    }

    private function passwordMatches(string $email, string $password): bool
    {
        $envEmail = env('ADMIN_EMAIL', 'admin@example.com');
        if ($email !== $envEmail) {
            return false;
        }

        $hash = $this->adminPasswordHash();
        if ($hash) {
            return Hash::check($password, $hash);
        }

        $envPassword = env('ADMIN_PASSWORD');
        return is_string($envPassword) && $envPassword !== '' && hash_equals($envPassword, $password);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'settings.update');

        $data = $request->validate([
            'default_storage' => ['nullable', 'string', 'in:local,oss,tencent,s3'],
            'max_file_size' => ['nullable', 'integer', 'min:1'],
            'default_expire_days' => ['nullable', 'integer', 'min:1'],
            'max_expire_days' => ['nullable', 'integer', 'min:1'],
            'allowed_file_types' => ['nullable', 'string'],
            // OSS配置
            'oss_enabled' => ['nullable', 'boolean'],
            'oss_access_key_id' => ['nullable', 'string', 'max:255'],
            'oss_access_key_secret' => ['nullable', 'string', 'max:255'],
            'oss_region' => ['nullable', 'string', 'max:64'],
            'oss_bucket' => ['nullable', 'string', 'max:255'],
            'oss_endpoint' => ['nullable', 'string', 'max:255'],
            'oss_url' => ['nullable', 'string', 'max:255'],
            // 腾讯云COS配置
            'tencent_enabled' => ['nullable', 'boolean'],
            'tencent_secret_id' => ['nullable', 'string', 'max:255'],
            'tencent_secret_key' => ['nullable', 'string', 'max:255'],
            'tencent_region' => ['nullable', 'string', 'max:64'],
            'tencent_bucket' => ['nullable', 'string', 'max:255'],
            'tencent_endpoint' => ['nullable', 'string', 'max:255'],
            'tencent_url' => ['nullable', 'string', 'max:255'],
            // AWS S3配置
            's3_enabled' => ['nullable', 'boolean'],
            's3_access_key_id' => ['nullable', 'string', 'max:255'],
            's3_secret_access_key' => ['nullable', 'string', 'max:255'],
            's3_region' => ['nullable', 'string', 'max:64'],
            's3_bucket' => ['nullable', 'string', 'max:255'],
            's3_endpoint' => ['nullable', 'string', 'max:255'],
            's3_url' => ['nullable', 'string', 'max:255'],
            'netdisk123_enabled' => ['nullable', 'boolean'],
            'netdisk123_username' => ['nullable', 'string', 'max:255'],
            'netdisk123_token' => ['nullable', 'string'],
            'netdisk123_cookie' => ['nullable', 'string'],
            'netdisk123_max_file_size' => ['nullable', 'integer', 'min:1'],
            'netdisk123_auto_share' => ['nullable', 'boolean'],
            'netdisk123_share_expire_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'risk_block_score' => ['nullable', 'integer', 'min:1', 'max:100'],
            'risk_false_positive_policy' => ['nullable', Rule::in(['require_ack', 'allow_direct'])],
            'chunked_upload_enabled' => ['nullable', 'boolean'],
            'chunked_upload_max_chunk_size' => ['nullable', 'integer', 'min:1'],
            'chunked_upload_max_chunks' => ['nullable', 'integer', 'min:1'],
            'chunked_upload_ttl_minutes' => ['nullable', 'integer', 'min:1'],
            'virus_scan_enabled' => ['nullable', 'boolean'],
            'virus_scan_clamav_path' => ['nullable', 'string', 'max:255'],
            'virus_scan_timeout_seconds' => ['nullable', 'integer', 'min:5'],
            'geetest_enabled' => ['nullable', 'boolean'],
            'geetest_captcha_id' => ['nullable', 'string', 'max:255'],
            'footer_icp_beian' => ['nullable', 'string', 'max:255'],
            'footer_gongan_beian' => ['nullable', 'string', 'max:255'],
            'footer_gongan_code' => ['nullable', 'string', 'max:255'],
            'footer_links' => ['nullable', 'string'],
            'lan_enabled' => ['nullable', 'boolean'],
            'lan_max_file_size' => ['nullable', 'integer', 'min:1'],
            'lan_max_file_count' => ['nullable', 'integer', 'min:1'],
            'lan_max_total_size' => ['nullable', 'integer', 'min:1'],
            'lan_expire_minutes' => ['nullable', 'integer', 'min:1'],
            'lan_text_enabled' => ['nullable', 'boolean'],
            'lan_text_max_length' => ['nullable', 'integer', 'min:1'],
            'lan_text_max_lines' => ['nullable', 'integer', 'min:1'],
            'lan_text_retention_minutes' => ['nullable', 'integer', 'min:1'],
            'app_enabled' => ['nullable', 'boolean'],
            'app_title' => ['nullable', 'string', 'max:255'],
            'app_subtitle' => ['nullable', 'string', 'max:255'],
            'app_description' => ['nullable', 'string'],
            'app_features' => ['nullable', 'string'],
            'app_android_enabled' => ['nullable', 'boolean'],
            'app_android_download_url' => ['nullable', 'string', 'max:2048'],
            'app_android_version' => ['nullable', 'string', 'max:64'],
            'app_ios_enabled' => ['nullable', 'boolean'],
            'app_ios_download_url' => ['nullable', 'string', 'max:2048'],
            'app_ios_version' => ['nullable', 'string', 'max:64'],
            'app_qrcode_enabled' => ['nullable', 'boolean'],
            'ops_alert_enabled' => ['nullable', 'boolean'],
            'ops_alert_webhook_url' => ['nullable', 'string', 'max:2048'],
            'ops_alert_min_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'confirm_text' => ['nullable', 'string'],
        ]);

        if (trim((string) ($data['ops_alert_webhook_url'] ?? '')) !== '' && ! str_starts_with(strtolower(trim((string) $data['ops_alert_webhook_url'])), 'https://')) {
            return back()->withErrors(['ops_alert_webhook_url' => '告警 Webhook 必须使用 https:// 地址。'])->withInput();
        }

        if (isset($data['max_file_size'])) {
            Setting::query()->updateOrCreate(['group' => 'upload', 'key' => 'max_file_size'], ['value' => (string) $data['max_file_size'], 'type' => 'int']);
        }
        if (isset($data['default_expire_days'])) {
            Setting::query()->updateOrCreate(['group' => 'upload', 'key' => 'default_expire_days'], ['value' => (string) $data['default_expire_days'], 'type' => 'int']);
        }
        if (isset($data['max_expire_days'])) {
            Setting::query()->updateOrCreate(['group' => 'upload', 'key' => 'max_expire_days'], ['value' => (string) $data['max_expire_days'], 'type' => 'int']);
        }
        if (isset($data['allowed_file_types'])) {
            Setting::query()->updateOrCreate(['group' => 'upload', 'key' => 'allowed_file_types'], ['value' => (string) $data['allowed_file_types'], 'type' => 'string']);
        }
        if (isset($data['risk_block_score'])) {
            Setting::query()->updateOrCreate(['group' => 'risk', 'key' => 'block_score'], ['value' => (string) $data['risk_block_score'], 'type' => 'int']);
        }
        Setting::query()->updateOrCreate(['group' => 'risk', 'key' => 'false_positive_policy'], ['value' => (string) ($data['risk_false_positive_policy'] ?? 'require_ack'), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'chunked_upload', 'key' => 'enabled'], ['value' => $request->boolean('chunked_upload_enabled') ? '1' : '0', 'type' => 'bool']);
        foreach ([
            'max_chunk_size' => 'chunked_upload_max_chunk_size',
            'max_chunks' => 'chunked_upload_max_chunks',
            'session_ttl_minutes' => 'chunked_upload_ttl_minutes',
        ] as $settingKey => $inputKey) {
            if (isset($data[$inputKey])) {
                Setting::query()->updateOrCreate(['group' => 'chunked_upload', 'key' => $settingKey], ['value' => (string) $data[$inputKey], 'type' => 'int']);
            }
        }
        Setting::query()->updateOrCreate(['group' => 'virus_scan', 'key' => 'enabled'], ['value' => $request->boolean('virus_scan_enabled') ? '1' : '0', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'virus_scan', 'key' => 'clamav_path'], ['value' => (string) ($data['virus_scan_clamav_path'] ?? ''), 'type' => 'string']);
        if (isset($data['virus_scan_timeout_seconds'])) {
            Setting::query()->updateOrCreate(['group' => 'virus_scan', 'key' => 'timeout_seconds'], ['value' => (string) $data['virus_scan_timeout_seconds'], 'type' => 'int']);
        }
        Setting::query()->updateOrCreate(['group' => 'geetest', 'key' => 'enabled'], ['value' => $request->boolean('geetest_enabled') ? '1' : '0', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'geetest', 'key' => 'captcha_id'], ['value' => (string) ($data['geetest_captcha_id'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'footer', 'key' => 'icp_beian'], ['value' => (string) ($data['footer_icp_beian'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'footer', 'key' => 'gongan_beian'], ['value' => (string) ($data['footer_gongan_beian'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'footer', 'key' => 'gongan_code'], ['value' => (string) ($data['footer_gongan_code'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'footer', 'key' => 'links'], ['value' => json_encode($this->footerLinks((string) ($data['footer_links'] ?? '')), JSON_UNESCAPED_UNICODE), 'type' => 'json']);
        if (isset($data['default_storage']) && ! $this->storageDiskReady((string) $data['default_storage'])) {
            return back()->withErrors(['default_storage' => '请先启用并补全该存储方式的必填配置，再设为默认存储。'])->withInput();
        }

        if (isset($data['default_storage'])) {
            $currentDisk = (string) Setting::valueFor('storage', 'default_disk', 'local');
            if ($currentDisk !== (string) $data['default_storage']) {
                $this->ensureConfirmation($request);
            }
            Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'default_disk'], ['value' => (string) $data['default_storage'], 'type' => 'string']);
        }

        // OSS配置
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'oss_enabled'], ['value' => $request->boolean('oss_enabled') ? '1' : '0', 'type' => 'bool']);
        $this->storeNonEmptySetting('storage', 'oss_access_key_id', $data['oss_access_key_id'] ?? null);
        $this->storeNonEmptySetting('storage', 'oss_access_key_secret', $data['oss_access_key_secret'] ?? null);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'oss_region'], ['value' => (string) ($data['oss_region'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'oss_bucket'], ['value' => (string) ($data['oss_bucket'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'oss_endpoint'], ['value' => (string) ($data['oss_endpoint'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'oss_url'], ['value' => (string) ($data['oss_url'] ?? ''), 'type' => 'string']);
        
        // 腾讯云COS配置
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'tencent_enabled'], ['value' => $request->boolean('tencent_enabled') ? '1' : '0', 'type' => 'bool']);
        $this->storeNonEmptySetting('storage', 'tencent_secret_id', $data['tencent_secret_id'] ?? null);
        $this->storeNonEmptySetting('storage', 'tencent_secret_key', $data['tencent_secret_key'] ?? null);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'tencent_region'], ['value' => (string) ($data['tencent_region'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'tencent_bucket'], ['value' => (string) ($data['tencent_bucket'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'tencent_endpoint'], ['value' => (string) ($data['tencent_endpoint'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 'tencent_url'], ['value' => (string) ($data['tencent_url'] ?? ''), 'type' => 'string']);
        
        // AWS S3配置
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 's3_enabled'], ['value' => $request->boolean('s3_enabled') ? '1' : '0', 'type' => 'bool']);
        $this->storeNonEmptySetting('storage', 's3_access_key_id', $data['s3_access_key_id'] ?? null);
        $this->storeNonEmptySetting('storage', 's3_secret_access_key', $data['s3_secret_access_key'] ?? null);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 's3_region'], ['value' => (string) ($data['s3_region'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 's3_bucket'], ['value' => (string) ($data['s3_bucket'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 's3_endpoint'], ['value' => (string) ($data['s3_endpoint'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'storage', 'key' => 's3_url'], ['value' => (string) ($data['s3_url'] ?? ''), 'type' => 'string']);

        Setting::query()->updateOrCreate(['group' => 'netdisk123', 'key' => 'enabled'], ['value' => $request->boolean('netdisk123_enabled') ? '1' : '0', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'netdisk123', 'key' => 'username'], ['value' => (string) ($data['netdisk123_username'] ?? ''), 'type' => 'string']);
        $this->storeNonEmptySetting('netdisk123', 'token', $data['netdisk123_token'] ?? null);
        $this->storeNonEmptySetting('netdisk123', 'cookie', $data['netdisk123_cookie'] ?? null);
        if (isset($data['netdisk123_max_file_size'])) {
            Setting::query()->updateOrCreate(['group' => 'netdisk123', 'key' => 'max_file_size'], ['value' => (string) $data['netdisk123_max_file_size'], 'type' => 'int']);
        }
        Setting::query()->updateOrCreate(['group' => 'netdisk123', 'key' => 'auto_share'], ['value' => $request->boolean('netdisk123_auto_share') ? '1' : '0', 'type' => 'bool']);
        if (isset($data['netdisk123_share_expire_days'])) {
            Setting::query()->updateOrCreate(['group' => 'netdisk123', 'key' => 'share_expire_days'], ['value' => (string) $data['netdisk123_share_expire_days'], 'type' => 'int']);
        }

        Setting::query()->updateOrCreate(['group' => 'lan_transfer', 'key' => 'enabled'], ['value' => $request->boolean('lan_enabled') ? '1' : '0', 'type' => 'bool']);
        foreach ([
            'max_file_size' => 'lan_max_file_size',
            'max_file_count' => 'lan_max_file_count',
            'max_total_size' => 'lan_max_total_size',
            'expire_minutes' => 'lan_expire_minutes',
            'text_max_length' => 'lan_text_max_length',
            'text_max_lines' => 'lan_text_max_lines',
            'text_retention_minutes' => 'lan_text_retention_minutes',
        ] as $settingKey => $inputKey) {
            if (isset($data[$inputKey])) {
                Setting::query()->updateOrCreate(['group' => 'lan_transfer', 'key' => $settingKey], ['value' => (string) $data[$inputKey], 'type' => 'int']);
            }
        }
        Setting::query()->updateOrCreate(['group' => 'lan_transfer', 'key' => 'text_enabled'], ['value' => $request->boolean('lan_text_enabled') ? '1' : '0', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'enabled'], ['value' => $request->boolean('app_enabled') ? '1' : '0', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'title'], ['value' => (string) ($data['app_title'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'subtitle'], ['value' => (string) ($data['app_subtitle'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'description'], ['value' => (string) ($data['app_description'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'features'], ['value' => json_encode($this->lines((string) ($data['app_features'] ?? '')), JSON_UNESCAPED_UNICODE), 'type' => 'json']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'android_enabled'], ['value' => $request->boolean('app_android_enabled') ? '1' : '0', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'android_download_url'], ['value' => (string) ($data['app_android_download_url'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'android_version'], ['value' => (string) ($data['app_android_version'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'ios_enabled'], ['value' => $request->boolean('app_ios_enabled') ? '1' : '0', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'ios_download_url'], ['value' => (string) ($data['app_ios_download_url'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'ios_version'], ['value' => (string) ($data['app_ios_version'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'app_download', 'key' => 'qrcode_enabled'], ['value' => $request->boolean('app_qrcode_enabled') ? '1' : '0', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'enabled'], ['value' => $request->boolean('ops_alert_enabled') ? '1' : '0', 'type' => 'bool']);
        Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'webhook_url'], ['value' => (string) ($data['ops_alert_webhook_url'] ?? ''), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'min_interval_minutes'], ['value' => (string) ($data['ops_alert_min_interval_minutes'] ?? 60), 'type' => 'int']);

        AuditLogger::write($request, 'settings.update', 'settings', null, [
            'groups' => ['upload', 'storage', 'risk', 'chunked_upload', 'virus_scan', 'geetest', 'footer', 'lan_transfer', 'app_download', 'netdisk123', 'ops_alert'],
            'secret_changed' => [
                'oss' => trim((string) ($data['oss_access_key_secret'] ?? '')) !== '',
                'tencent' => trim((string) ($data['tencent_secret_key'] ?? '')) !== '',
                's3' => trim((string) ($data['s3_secret_access_key'] ?? '')) !== '',
                'netdisk123_token' => trim((string) ($data['netdisk123_token'] ?? '')) !== '',
                'netdisk123_cookie' => trim((string) ($data['netdisk123_cookie'] ?? '')) !== '',
            ],
            'default_storage' => $data['default_storage'] ?? null,
        ]);

        return back()->with('status', '配置已保存');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'settings.update');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! $this->adminPasswordMatches($data['current_password'])) {
            return back()->withErrors(['current_password' => '当前密码不正确']);
        }

        Setting::query()->updateOrCreate(
            ['group' => 'admin', 'key' => 'password_hash'],
            ['value' => Hash::make($data['password']), 'type' => 'string', 'description' => '后台 Basic Auth 密码 hash'],
        );

        AuditLogger::write($request, 'admin.password.update', 'settings', null);

        return back()->with('status', '管理员密码已更新');
    }

    private function storeNonEmptySetting(string $group, string $key, mixed $value): void
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return;
        }

        Setting::query()->updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value, 'type' => 'string']);
    }

    private function storageDiskReady(string $disk): bool
    {
        if ($disk === 'local') {
            return true;
        }

        $required = match ($disk) {
            'oss' => ['oss_enabled', 'oss_access_key_id', 'oss_access_key_secret', 'oss_region', 'oss_bucket', 'oss_endpoint'],
            'tencent' => ['tencent_enabled', 'tencent_secret_id', 'tencent_secret_key', 'tencent_region', 'tencent_bucket', 'tencent_endpoint'],
            's3' => ['s3_enabled', 's3_access_key_id', 's3_secret_access_key', 's3_region', 's3_bucket'],
            default => [],
        };

        foreach ($required as $key) {
            $value = Setting::valueFor('storage', $key, '');
            if (str_ends_with($key, '_enabled')) {
                if (! (bool) $value) {
                    return false;
                }
                continue;
            }

            if (trim((string) $value) === '') {
                return false;
            }
        }

        return $required !== [];
    }

    public function updateContent(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'settings.update');

        $data = $request->validate([
            'content_type' => ['required', 'in:terms,privacy'],
            'content_body' => ['nullable', 'string'],
        ]);

        Setting::query()->updateOrCreate(
            ['group' => 'content', 'key' => $data['content_type']],
            ['value' => (string) ($data['content_body'] ?? ''), 'type' => 'text'],
        );

        AuditLogger::write($request, 'content.update', 'settings', null, ['type' => $data['content_type']]);

        $label = $data['content_type'] === 'terms' ? '用户协议' : '隐私政策';

        return back()->with('status', $label . '已保存');
    }

    public function storeAdminUser(Request $request): RedirectResponse
    {
        $this->ensureCanManageAdmins($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:owner,admin,viewer'],
            'permissions' => ['nullable', 'string'],
        ]);

        if ($data['role'] === 'owner' && $request->attributes->get('admin_role') !== 'owner') {
            return back()->withErrors(['role' => '只有 owner 可以授予 owner 角色'])->withInput($request->except('password'));
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => true,
            'role' => $data['role'],
            'permissions_json' => $this->permissions((string) ($data['permissions'] ?? '')),
            'status' => 'active',
        ]);

        AuditLogger::write($request, 'admin_user.create', User::class, $user->id, ['email' => $user->email, 'role' => $user->role]);

        return back()->with('status', '管理员已新增');
    }

    public function editAdminUser(Request $request, User $user): View
    {
        $this->ensureCanManageAdmins($request);

        abort_unless($user->is_admin, 404);

        return view('admin.user-edit', [
            'adminUser' => $user,
            'currentAdmin' => $request->attributes->get('admin_user'),
        ]);
    }

    public function updateAdminUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManageAdmins($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'in:owner,admin,viewer'],
            'status' => ['required', 'in:active,disabled'],
            'permissions' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        if (($user->role === 'owner' || $data['role'] === 'owner') && $request->attributes->get('admin_role') !== 'owner') {
            return back()->withErrors(['role' => '只有 owner 可以修改 owner 管理员'])->withInput($request->except('password'));
        }

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => true,
            'role' => $data['role'],
            'status' => $data['status'],
            'permissions_json' => $this->permissions((string) ($data['permissions'] ?? '')),
        ];
        if (filled($data['password'] ?? null)) {
            $updates['password'] = $data['password'];
        }

        $user->update($updates);
        AuditLogger::write($request, 'admin_user.update', User::class, $user->id, ['email' => $user->email, 'role' => $user->role, 'status' => $user->status]);

        if ((int) $request->session()->get('admin_user_id') === (int) $user->id) {
            $request->session()->put('admin_email', $user->email);
            $request->session()->put('admin_role', $user->role ?: 'admin');
        }

        return redirect()->route('admin-lite.users.edit', $user)->with('status', '管理员已更新');
    }

    public function deleteAdminUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManageAdmins($request);

        $current = $request->attributes->get('admin_user');
        if ($current instanceof User && $current->is($user)) {
            return back()->withErrors(['admin_user' => '不能停用当前登录账号']);
        }

        if ($user->role === 'owner' && $request->attributes->get('admin_role') !== 'owner') {
            return back()->withErrors(['admin_user' => '只有 owner 可以停用 owner 管理员']);
        }

        $user->update(['is_admin' => false, 'status' => 'disabled']);
        AuditLogger::write($request, 'admin_user.disable', User::class, $user->id, ['email' => $user->email]);

        return back()->with('status', '管理员已停用');
    }

    public function logout(Request $request): RedirectResponse
    {
        return $this->processLogout($request);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'settings.update');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', 'string', 'max:32'],
            'priority' => ['required', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
        ]);

        $announcement = Announcement::query()->create([
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'],
            'priority' => $data['priority'],
            'is_active' => $request->boolean('is_active'),
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
        ]);

        AuditLogger::write($request, 'announcement.create', Announcement::class, $announcement->id, $announcement->only(['title', 'type', 'priority']));

        return back()->with('status', '公告已新增');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->ensureCan($request, 'settings.update');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['required', 'string', 'max:32'],
            'priority' => ['required', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date'],
        ]);

        $announcement->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'],
            'priority' => $data['priority'],
            'is_active' => $request->boolean('is_active'),
            'start_at' => $data['start_at'] ?? null,
            'end_at' => $data['end_at'] ?? null,
        ]);

        AuditLogger::write($request, 'announcement.update', Announcement::class, $announcement->id);

        return back()->with('status', '公告已更新');
    }

    public function deleteAnnouncement(Request $request, Announcement $announcement): RedirectResponse
    {
        $this->ensureCan($request, 'settings.update');

        $announcement->delete();
        AuditLogger::write($request, 'announcement.delete', Announcement::class, $announcement->id);

        return back()->with('status', '公告已删除');
    }

    public function storeBlockedIp(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'files.block');

        $data = $request->validate([
            'ip' => ['required', 'ip'],
            'reason' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', 'in:all,upload,download'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $blockedIp = BlockedIp::query()->create($data);
        AuditLogger::write($request, 'blocked_ip.create', BlockedIp::class, $blockedIp->id, $blockedIp->only(['ip', 'scope']));

        return back()->with('status', '封禁已新增');
    }

    public function deleteBlockedIp(Request $request, BlockedIp $blockedIp): RedirectResponse
    {
        $this->ensureCan($request, 'files.block');
        $this->ensureConfirmation($request);

        $blockedIp->forceFill(['expires_at' => now()])->save();
        AuditLogger::write($request, 'blocked_ip.delete', BlockedIp::class, $blockedIp->id, $blockedIp->only(['ip', 'scope']));

        return back()->with('status', '封禁已解除');
    }

    public function deleteFile(Request $request, SharedFile $file): RedirectResponse
    {
        $this->ensureCan($request, 'files.delete');
        $this->ensureConfirmation($request);

        DeleteStoredFile::dispatch($file->id);
        $file->update(['status' => 'deleted']);
        $file->delete();
        AuditLogger::write($request, 'file.delete', SharedFile::class, $file->id, ['code' => $file->code]);

        return $this->backToFiles('文件已加入删除队列，稍后会清理存储对象');
    }

    public function blockFile(Request $request, SharedFile $file): RedirectResponse
    {
        $this->ensureCan($request, 'files.block');
        $this->ensureConfirmation($request);

        $file->update(['status' => 'blocked']);
        AuditLogger::write($request, 'file.block', SharedFile::class, $file->id, ['code' => $file->code]);

        return $this->backToFiles('文件已封禁，用户将无法下载或预览');
    }

    public function extendFile(Request $request, SharedFile $file): RedirectResponse
    {
        $this->ensureCan($request, 'files.extend');

        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365']]);
        $base = $file->expires_at && $file->expires_at->isFuture() ? $file->expires_at : now();
        $file->update(['status' => 'active', 'expires_at' => $base->copy()->addDays($data['days'])]);
        AuditLogger::write($request, 'file.extend', SharedFile::class, $file->id, ['code' => $file->code, 'days' => $data['days']]);

        return $this->backToFiles('过期时间已延长 '.$data['days'].' 天');
    }

    public function rescanFile(Request $request, SharedFile $file): RedirectResponse
    {
        $this->ensureCan($request, 'files.rescan');
        $this->ensureConfirmation($request);

        $file->forceFill([
            'scan_status' => 'pending',
            'scan_result' => null,
            'scan_checked_at' => null,
            'malware_scan_passed' => false,
            'malware_scan_checked_at' => null,
            'malware_scan_details' => null,
        ])->save();

        ScanUploadedFile::dispatch($file->id);
        AuditLogger::write($request, 'file.rescan', SharedFile::class, $file->id, ['code' => $file->code]);

        return $this->backToFiles('已提交重新扫描任务，扫描完成前文件会保持风险拦截');
    }

    public function bulkFiles(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['rescan', 'block', 'extend'])],
            'file_ids' => ['required', 'array', 'min:1', 'max:100'],
            'file_ids.*' => ['integer', 'min:1'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'confirm_text' => ['nullable', 'string'],
        ]);

        $files = SharedFile::query()
            ->whereIn('id', collect($data['file_ids'])->unique()->take(100)->values())
            ->get();
        if ($files->isEmpty()) {
            return back()->withErrors(['files' => '请选择要操作的文件']);
        }

        $this->ensureCan($request, match ($data['action']) {
            'block' => 'files.block',
            'extend' => 'files.extend',
            default => 'files.rescan',
        });
        if (in_array($data['action'], ['block', 'rescan'], true)) {
            $this->ensureConfirmation($request);
        }

        if ($data['action'] === 'block') {
            SharedFile::query()->whereIn('id', $files->pluck('id'))->update(['status' => 'blocked']);
        }

        if ($data['action'] === 'extend') {
            $days = (int) ($data['days'] ?? 1);
            foreach ($files as $file) {
                $base = $file->expires_at && $file->expires_at->isFuture() ? $file->expires_at : now();
                $file->update(['status' => 'active', 'expires_at' => $base->copy()->addDays($days)]);
            }
        }

        if ($data['action'] === 'rescan') {
            foreach ($files as $file) {
                $file->forceFill([
                    'scan_status' => 'pending',
                    'scan_result' => null,
                    'scan_checked_at' => null,
                    'malware_scan_passed' => false,
                    'malware_scan_checked_at' => null,
                    'malware_scan_details' => null,
                ])->save();
                ScanUploadedFile::dispatch($file->id);
            }
        }

        AuditLogger::write($request, 'file.bulk_'.$data['action'], SharedFile::class, null, [
            'count' => $files->count(),
            'file_ids' => $files->pluck('id')->values()->all(),
        ]);

        return $this->backToFiles('批量操作已完成，共处理 '.$files->count().' 个文件');
    }

    public function runLanCleanup(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'maintenance.run');

        Artisan::call('yeyu-file-express:cleanup-lan-sessions');
        AuditLogger::write($request, 'maintenance.cleanup_lan', 'maintenance', null, []);

        return back()->with('status', trim(Artisan::output()) ?: '局域网互传会话清理完成');
    }

    public function runLogPrune(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'maintenance.run');

        $days = max(7, min(365, (int) $request->input('days', 30)));
        Artisan::call('yeyu-file-express:prune-logs', ['--days' => $days]);
        AuditLogger::write($request, 'maintenance.prune_logs', 'maintenance', null, ['days' => $days]);

        return back()->with('status', trim(Artisan::output()) ?: '运行日志清理完成');
    }

    public function runOpsCheck(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'maintenance.run');

        Artisan::call('yeyu-file-express:ops-check', ['--record' => true]);
        AuditLogger::write($request, 'maintenance.ops_check', 'maintenance', null, []);

        return redirect()->route('admin-lite.dashboard', ['tab' => 'security'])->with('status', trim(Artisan::output()) ?: '运维自检已执行');
    }

    public function testOpsAlert(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'settings.update');
        $alert = $this->opsAlertSettings();
        $url = trim((string) ($alert['webhookUrl'] ?? ''));
        if ($url === '') {
            return back()->withErrors(['ops_alert' => '请先配置 Webhook URL']);
        }

        try {
            $response = Http::timeout(5)->acceptJson()->post($url, [
                'title' => '叶宇文件快递运维告警测试',
                'status' => 'test',
                'checked_at' => now()->toDateTimeString(),
                'message' => 'Webhook 测试消息',
            ]);
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_tested_at'], ['value' => now()->toDateTimeString(), 'type' => 'string']);
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_test_status_code'], ['value' => (string) $response->status(), 'type' => 'int']);
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_test_error'], ['value' => $response->successful() ? '' : mb_substr($response->body(), 0, 500), 'type' => 'string']);
            AuditLogger::write($request, 'ops_alert.test', 'settings', null, ['status_code' => $response->status()]);

            return back()->with('status', '告警测试已发送，响应码：'.$response->status());
        } catch (Throwable $e) {
            Setting::query()->updateOrCreate(['group' => 'ops_alert', 'key' => 'last_test_error'], ['value' => mb_substr($e->getMessage(), 0, 500), 'type' => 'string']);

            return back()->withErrors(['ops_alert' => '告警测试失败：'.mb_substr($e->getMessage(), 0, 200)]);
        }
    }

    public function exportAuditLogs(Request $request): Response
    {
        $this->ensureCan($request, 'maintenance.run');
        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();
        $action = trim((string) $request->query('action', ''));
        $target = trim((string) $request->query('target', ''));
        $ip = trim((string) $request->query('ip', ''));
        $rows = AuditLog::query()
            ->when($from, fn (Builder $query) => $query->where('created_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('created_at', '<=', $to))
            ->when($action !== '', fn (Builder $query) => $query->where('action', 'like', '%'.$action.'%'))
            ->when($target !== '', function (Builder $query) use ($target): void {
                $query->where(function (Builder $query) use ($target): void {
                    $query->where('target_type', 'like', '%'.$target.'%');
                    if (ctype_digit($target)) {
                        $query->orWhere('target_id', (int) $target);
                    }
                });
            })
            ->when($ip !== '', fn (Builder $query) => $query->where('ip', 'like', '%'.$ip.'%'))
            ->latest('created_at')
            ->limit(5000)
            ->get();
        $lines = ['created_at,action,target_type,target_id,ip,user_agent,metadata'];
        foreach ($rows as $row) {
            $lines[] = collect([
                $row->created_at,
                $row->action,
                $row->target_type,
                $row->target_id,
                $row->ip,
                $row->user_agent,
                json_encode($row->metadata_json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ])->map(fn ($value): string => '"'.str_replace('"', '""', (string) $value).'"')->implode(',');
        }
        AuditLogger::write($request, 'audit.export', AuditLog::class, null, ['count' => $rows->count()]);

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="audit-logs.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function retryFailedScan(Request $request, int $failedJobId): RedirectResponse
    {
        $this->ensureCan($request, 'files.rescan');
        $this->ensureConfirmation($request);

        abort_unless(Schema::hasTable('failed_jobs'), 404);

        $failedJob = DB::table('failed_jobs')->where('id', $failedJobId)->first();
        abort_unless($failedJob, 404);

        $fileId = $this->extractScanFileId((string) $failedJob->payload);
        if (! $fileId) {
            return back()->withErrors(['failed_job' => '该失败任务未识别到可重派发的扫描文件']);
        }

        $file = SharedFile::query()->find($fileId);
        if (! $file) {
            return back()->withErrors(['failed_job' => '扫描文件不存在']);
        }

        $file->forceFill([
            'scan_status' => 'pending',
            'scan_result' => null,
            'scan_checked_at' => null,
            'malware_scan_passed' => false,
            'malware_scan_checked_at' => null,
            'malware_scan_details' => null,
        ])->save();
        ScanUploadedFile::dispatch($file->id);

        AuditLogger::write($request, 'failed_job.retry_scan', SharedFile::class, $file->id, [
            'failed_job_id' => $failedJobId,
            'code' => $file->code,
        ]);

        return redirect()->route('admin-lite.dashboard', ['tab' => 'security'])->with('status', '已重新派发扫描任务：'.$file->code.'，扫描完成前文件会保持风险拦截');
    }

    public function exportRiskReport(Request $request, SharedFile $file): Response
    {
        $this->ensureCan($request, 'files.review');

        abort_unless((bool) $file->malware_scan_checked_at && ! (bool) $file->malware_scan_passed, 404);

        $details = is_array($file->malware_scan_details) ? $file->malware_scan_details : json_decode((string) $file->malware_scan_details, true);
        $acks = Schema::hasTable('risk_download_ack_logs')
            ? RiskDownloadAckLog::query()->where('file_id', $file->id)->latest('created_at')->limit(100)->get()
            : collect();
        $lines = [
            '# 风险文件安全报告',
            '',
            '- 分享码：'.$file->code,
            '- 文件名：'.$file->original_name,
            '- 文件大小：'.number_format((int) $file->size).' bytes',
            '- MIME：'.($file->mime_type ?: '-'),
            '- 风险评分：'.((int) ($file->risk_score ?? 0)).' / 100',
            '- 扫描时间：'.($file->malware_scan_checked_at ? (string) $file->malware_scan_checked_at : '-'),
            '',
            '## 风险评分依据',
        ];
        foreach (($file->risk_reasons_json ?: []) as $reason) {
            $lines[] = '- '.(string) $reason;
        }
        $lines[] = '';
        $lines[] = '## 威胁类型';
        foreach (array_values(array_unique(array_filter(array_merge($details['threats'] ?? [], $details['threat_types'] ?? [])))) as $threat) {
            $lines[] = '- '.(string) $threat;
        }
        $lines[] = '';
        $lines[] = '## 命中文件';
        foreach (($details['files'] ?? []) as $name) {
            $lines[] = '- '.(string) $name;
        }
        foreach (($details['details']['archive_scan']['files'] ?? []) as $entry) {
            if (($entry['is_malicious'] ?? false) === true) {
                $lines[] = '- '.(string) ($entry['name'] ?? '未知文件').'：'.(string) ($entry['reason'] ?? '已判定为高风险');
            }
        }
        $lines[] = '';
        $lines[] = '## 风险下载确认记录';
        foreach ($acks as $ack) {
            $lines[] = '- '.$ack->created_at.' | IP '.$ack->ip.' | 风险分 '.$ack->risk_score;
        }

        AuditLogger::write($request, 'risk_report.export', SharedFile::class, $file->id, ['code' => $file->code]);

        $filename = 'risk-report-'.$file->code.'.md';
        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function sourceFile(Request $request, SharedFile $file)
    {
        $this->ensureCan($request, 'files.review');

        abort_unless((bool) $file->malware_scan_checked_at && ! (bool) $file->malware_scan_passed, 404);

        if (! Storage::disk($file->disk)->exists($file->path)) {
            return response('源文件不存在或存储不可访问', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $mode = $request->query('mode') === 'download' ? 'download' : 'preview';
        if ($mode === 'preview' && $this->canWatermarkSourcePreview($file)) {
            AuditLogger::write($request, 'risk_file.source_view', SharedFile::class, $file->id, [
                'code' => $file->code,
                'mode' => $mode,
                'watermarked' => true,
            ]);

            return $this->watermarkedImagePreview($request, $file);
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);
        abort_unless(is_resource($stream), 404);

        AuditLogger::write($request, 'risk_file.source_view', SharedFile::class, $file->id, [
            'code' => $file->code,
            'mode' => $mode,
        ]);

        $disposition = $mode === 'download' ? 'attachment' : 'inline';

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) $file->size,
            'Content-Disposition' => $disposition.'; filename="'.$this->safeHeaderFilename($file->original_name).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function bulkReviewRiskFiles(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'files.review');

        $data = $request->validate([
            'file_ids' => ['required', 'array', 'min:1', 'max:100'],
            'file_ids.*' => ['integer', 'min:1'],
            'review_status' => ['required', 'in:pending,confirmed,false_positive,rescanned'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $files = SharedFile::query()
            ->whereIn('id', collect($data['file_ids'])->unique()->take(100)->values())
            ->where('malware_scan_passed', false)
            ->whereNotNull('malware_scan_checked_at')
            ->get();

        if ($files->isEmpty()) {
            return back()->withErrors(['risk_review' => '请选择可复核的风险文件']);
        }

        $note = trim((string) ($data['review_note'] ?? ''));
        foreach ($files as $file) {
            Setting::query()->updateOrCreate(
                ['group' => 'risk_review', 'key' => (string) $file->id],
                ['value' => json_encode([
                    'status' => $data['review_status'],
                    'note' => $note,
                    'reviewed_at' => now()->toDateTimeString(),
                    'reviewed_by' => $request->session()->get('admin_email'),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'type' => 'json'],
            );

            if ($data['review_status'] === 'rescanned') {
                $file->forceFill([
                    'scan_status' => 'pending',
                    'scan_result' => null,
                    'scan_checked_at' => null,
                    'malware_scan_passed' => false,
                    'malware_scan_checked_at' => null,
                    'malware_scan_details' => null,
                ])->save();
                ScanUploadedFile::dispatch($file->id);
            }
        }

        AuditLogger::write($request, 'risk_file.bulk_review', SharedFile::class, null, [
            'count' => $files->count(),
            'file_ids' => $files->pluck('id')->values()->all(),
            'status' => $data['review_status'],
            'note_present' => $note !== '',
        ]);

        return redirect()->route('admin-lite.dashboard', ['tab' => 'security'])->with('status', '批量复核已完成，共处理 '.$files->count().' 个风险文件');
    }

    public function reviewRiskFile(Request $request, SharedFile $file): RedirectResponse
    {
        $this->ensureCan($request, 'files.review');
        abort_unless((bool) $file->malware_scan_checked_at && ! (bool) $file->malware_scan_passed, 404);

        $data = $request->validate([
            'review_status' => ['required', 'in:pending,confirmed,false_positive,rescanned'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        Setting::query()->updateOrCreate(
            ['group' => 'risk_review', 'key' => (string) $file->id],
            ['value' => json_encode([
                'status' => $data['review_status'],
                'note' => trim((string) ($data['review_note'] ?? '')),
                'reviewed_at' => now()->toDateTimeString(),
                'reviewed_by' => $request->session()->get('admin_email'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'type' => 'json'],
        );

        AuditLogger::write($request, 'risk_file.review', SharedFile::class, $file->id, [
            'code' => $file->code,
            'status' => $data['review_status'],
            'note_present' => trim((string) ($data['review_note'] ?? '')) !== '',
        ]);

        if ($data['review_status'] === 'rescanned') {
            $file->forceFill([
                'scan_status' => 'pending',
                'scan_result' => null,
                'scan_checked_at' => null,
                'malware_scan_passed' => false,
                'malware_scan_checked_at' => null,
                'malware_scan_details' => null,
            ])->save();
            ScanUploadedFile::dispatch($file->id);
        }

        return back()->with('status', '风险文件复核状态已更新：'.$file->code);
    }

    public function closeBatchShare(Request $request, string $token): RedirectResponse
    {
        $this->ensureCan($request, 'files.review');
        $setting = Setting::query()->where('group', 'batch_share')->where('key', $token)->latest('updated_at')->first();
        abort_unless($setting, 404);
        $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);
        $decoded = is_array($decoded) ? $decoded : [];
        $decoded['closed_at'] = now()->toDateTimeString();
        $decoded['closed_by'] = $request->session()->get('admin_email');
        $setting->forceFill([
            'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'type' => 'json',
        ])->save();

        AuditLogger::write($request, 'batch_share.close', Setting::class, $setting->id, [
            'token' => $setting->key,
            'count' => count($decoded['codes'] ?? []),
        ]);

        return redirect()->route('admin-lite.dashboard', ['tab' => 'security'])->with('status', '批量分享已关闭：'.$setting->key);
    }

    public function reviewAppeal(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'files.review');

        $data = $request->validate([
            'appeal_key' => ['required', 'string', 'max:255'],
            'appeal_status' => ['required', Rule::in(['approved', 'rejected', 'needs_more_info'])],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $appeal = Setting::query()->where('group', 'risk_appeal')->where('key', $data['appeal_key'])->firstOrFail();
        $decoded = is_array($appeal->value) ? $appeal->value : json_decode((string) $appeal->value, true);
        abort_unless(is_array($decoded), 422);

        $decoded['status'] = $data['appeal_status'];
        $decoded['review_note'] = trim((string) ($data['review_note'] ?? ''));
        $decoded['reviewed_at'] = now()->toDateTimeString();
        $decoded['reviewed_by'] = $request->session()->get('admin_email');
        $appeal->forceFill([
            'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'type' => 'json',
        ])->save();

        $file = ! empty($decoded['file_id']) ? SharedFile::query()->find((int) $decoded['file_id']) : null;
        if ($file instanceof SharedFile) {
            $this->writeUserNotice($file->code, 'appeal_'.$data['appeal_status'], match ($data['appeal_status']) {
                'approved' => '你的申诉已通过，管理员会按复核结论处理文件。',
                'rejected' => '你的申诉已驳回，文件继续保持风险拦截。',
                default => '你的申诉需要补充信息，请根据备注重新提交。',
            }, $decoded['review_note']);

            if ($data['appeal_status'] === 'approved') {
                Setting::query()->updateOrCreate(
                    ['group' => 'risk_review', 'key' => (string) $file->id],
                    ['value' => json_encode([
                        'status' => 'false_positive',
                        'note' => trim((string) ($data['review_note'] ?? '申诉通过')),
                        'reviewed_at' => now()->toDateTimeString(),
                        'reviewed_by' => $request->session()->get('admin_email'),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'type' => 'json'],
                );
            }
        }

        AuditLogger::write($request, 'risk_appeal.review', $file instanceof SharedFile ? SharedFile::class : 'risk_appeal', $file?->id, [
            'appeal_key' => $data['appeal_key'],
            'status' => $data['appeal_status'],
        ]);

        return redirect()->route('admin-lite.dashboard', ['tab' => 'security'])->with('status', '申诉处理结果已保存');
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\R/u', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function footerLinks(string $value): array
    {
        return collect(preg_split('/\R/u', $value) ?: [])
            ->map(fn (string $line): array => array_map('trim', explode('|', $line)))
            ->filter(fn (array $parts): bool => count($parts) >= 2 && $parts[0] !== '' && $parts[1] !== '')
            ->values()
            ->map(fn (array $parts, int $index): array => [
                'id' => 'custom-'.$index,
                'type' => 'link',
                'text' => $parts[0],
                'href' => $parts[1],
                'meta' => '',
                'icon' => '',
                'enabled' => ! isset($parts[2]) || ! in_array(strtolower($parts[2]), ['0', 'false', 'off', 'disabled'], true),
                'sort' => isset($parts[3]) ? (int) $parts[3] : 10 + $index,
            ])
            ->all();
    }

    private function permissions(string $value): array
    {
        return $this->lines($value);
    }

    private function canManageAdmins(Request $request): bool
    {
        if ($request->attributes->get('admin_role') === 'owner') {
            return true;
        }

        $admin = $request->attributes->get('admin_user');
        if (! $admin instanceof User) {
            return false;
        }

        $permissions = $admin->permissions_json ?: [];

        return $admin->role === 'owner' || in_array('admins.manage', $permissions, true);
    }

    private function can(Request $request, string $permission): bool
    {
        $role = $request->attributes->get('admin_role');
        if ($role === 'owner') {
            return true;
        }

        $admin = $request->attributes->get('admin_user');
        $permissions = $admin instanceof User ? ($admin->permissions_json ?: []) : [];
        if (in_array($permission, $permissions, true)) {
            return true;
        }

        if ($role === 'admin') {
            return in_array($permission, [
                'files.block',
                'files.rescan',
                'files.extend',
                'files.review',
                'maintenance.run',
            ], true);
        }

        return false;
    }

    private function ensureCan(Request $request, string $permission): void
    {
        abort_unless($this->can($request, $permission), 403);
    }

    private function ensureConfirmation(Request $request, string $expected = 'CONFIRM'): void
    {
        abort_unless(hash_equals($expected, trim((string) $request->input('confirm_text', ''))), 422, '请输入确认词 '.$expected);
    }

    private function adminPermissionFlags(Request $request): array
    {
        $permissions = ['files.delete', 'files.block', 'files.rescan', 'files.extend', 'files.review', 'settings.update', 'ai.update', 'maintenance.run', 'admins.manage'];

        return collect($permissions)
            ->mapWithKeys(fn (string $permission): array => [$permission => $this->can($request, $permission)])
            ->all();
    }

    private function adminPermissionLabels(): array
    {
        return [
            'files.delete' => '删除文件',
            'files.block' => '封禁与解封',
            'files.rescan' => '重新扫描',
            'files.extend' => '延长有效期',
            'files.review' => '风险复核',
            'settings.update' => '系统配置',
            'ai.update' => 'AI 配置',
            'maintenance.run' => '运维清理',
            'admins.manage' => '管理员管理',
        ];
    }

    private function safeHeaderFilename(string $name): string
    {
        $safe = preg_replace('/[\r\n"\\\\]+/', '_', $name);

        return trim((string) $safe) !== '' ? (string) $safe : 'file';
    }

    private function canWatermarkSourcePreview(SharedFile $file): bool
    {
        $mime = strtolower((string) $file->mime_type);

        return str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml' && (int) $file->size <= 10 * 1024 * 1024;
    }

    private function watermarkedImagePreview(Request $request, SharedFile $file): Response
    {
        $contents = Storage::disk($file->disk)->get($file->path);
        $mime = preg_match('/^image\/[a-z0-9.+-]+$/i', (string) $file->mime_type) === 1 ? (string) $file->mime_type : 'image/jpeg';
        $label = '审核专用 '.$file->code.' #'.$file->id.' '.$request->ip().' '.now()->format('Y-m-d H:i:s');
        $encodedLabel = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $dataUri = 'data:'.$mime.';base64,'.base64_encode($contents);
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="900" viewBox="0 0 1200 900">
  <rect width="1200" height="900" fill="#111827"/>
  <image href="{$dataUri}" x="0" y="0" width="1200" height="900" preserveAspectRatio="xMidYMid meet"/>
  <g opacity="0.58" transform="rotate(-28 600 450)">
    <text x="-160" y="180" font-family="Arial, sans-serif" font-size="42" font-weight="700" fill="#ffffff">{$encodedLabel}</text>
    <text x="120" y="420" font-family="Arial, sans-serif" font-size="42" font-weight="700" fill="#ffffff">{$encodedLabel}</text>
    <text x="420" y="660" font-family="Arial, sans-serif" font-size="42" font-weight="700" fill="#ffffff">{$encodedLabel}</text>
  </g>
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Content-Disposition' => 'inline; filename="review-watermark-'.$this->safeHeaderFilename($file->code).'.svg"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function currentAdminSummary(Request $request): array
    {
        $admin = $request->attributes->get('admin_user');

        return [
            'email' => $admin instanceof User ? $admin->email : (string) $request->session()->get('admin_email', 'env-admin'),
            'role' => (string) $request->attributes->get('admin_role', 'owner'),
        ];
    }

    private function riskReviewStats(array $riskReviewStates): array
    {
        $riskFileIds = SharedFile::query()->where('malware_scan_passed', false)->pluck('id')->map(fn ($id): int => (int) $id);
        $counts = ['total' => $riskFileIds->count(), 'pending' => 0, 'confirmed' => 0, 'false_positive' => 0, 'rescanned' => 0];
        $counts['overdue'] = 0;
        foreach ($riskFileIds as $fileId) {
            $status = $riskReviewStates[$fileId]['status'] ?? 'pending';
            $counts[array_key_exists($status, $counts) ? $status : 'pending']++;
        }
        $counts['overdue'] = SharedFile::query()
            ->where('malware_scan_passed', false)
            ->whereNotNull('malware_scan_checked_at')
            ->where('malware_scan_checked_at', '<=', now()->subDay())
            ->get(['id'])
            ->filter(fn (SharedFile $file): bool => ($riskReviewStates[$file->id]['status'] ?? 'pending') === 'pending')
            ->count();
        $counts['scan_pending'] = SharedFile::query()->whereNull('malware_scan_checked_at')->count();

        return $counts;
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

    private function riskAppeals(): array
    {
        return Setting::query()
            ->where('group', 'risk_appeal')
            ->latest('updated_at')
            ->limit(200)
            ->get(['key', 'value'])
            ->reduce(function (array $appeals, Setting $setting): array {
                $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);
                if (! is_array($decoded) || empty($decoded['file_id'])) {
                    return $appeals;
                }

                $fileId = (int) $decoded['file_id'];
                $appeals[$fileId] ??= ['count' => 0, 'latest' => null];
                $appeals[$fileId]['count']++;
                if ($appeals[$fileId]['latest'] === null) {
                    $decoded['key'] = $setting->key;
                    $appeals[$fileId]['latest'] = $decoded;
                }

                return $appeals;
            }, []);
    }

    private function operationsHealth(): array
    {
        $jobsCount = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        $failedJobsCount = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;
        $actionableFailedJobsCount = $this->actionableFailedScanJobsCount();
        $lastProcessedAt = AuditLog::query()
            ->whereIn('action', ['file.rescan', 'file.bulk_rescan', 'failed_job.retry_scan', 'maintenance.cleanup_lan', 'maintenance.prune_logs'])
            ->latest('created_at')
            ->value('created_at');
        $aiFailureCount = AIScanLog::query()->where('skipped', true)->where('created_at', '>=', now()->subDay())->count();
        $heartbeatAt = Setting::valueFor('queue', 'worker_heartbeat_at', null);
        $opsCheckResult = $this->opsCheckResult();

        return [
            'jobsCount' => $jobsCount,
            'failedJobsCount' => $failedJobsCount,
            'actionableFailedJobsCount' => $actionableFailedJobsCount,
            'lastProcessedAt' => $lastProcessedAt,
            'aiFailureCount24h' => $aiFailureCount,
            'storageWritable' => [
                'storage_app' => is_writable(storage_path('app')),
                'framework_cache' => is_writable(storage_path('framework/cache')),
                'logs' => is_writable(storage_path('logs')),
            ],
            'maintenance' => [
                'lanCleanupLastAt' => Setting::valueFor('maintenance', 'lan_cleanup_last_at', null),
                'lanCleanupLastSummary' => Setting::valueFor('maintenance', 'lan_cleanup_last_summary', null),
                'logPruneLastAt' => Setting::valueFor('maintenance', 'log_prune_last_at', null),
                'logPruneLastSummary' => Setting::valueFor('maintenance', 'log_prune_last_summary', null),
            ],
            'queueHeartbeat' => [
                'at' => $heartbeatAt,
                'fresh' => $heartbeatAt ? strtotime((string) $heartbeatAt) >= now()->subMinutes(3)->timestamp : false,
            ],
            'opsCheck' => [
                'checkedAt' => Setting::valueFor('ops_check', 'last_checked_at', null),
                'status' => Setting::valueFor('ops_check', 'last_status', 'unknown'),
                'issues' => is_array($opsCheckResult['issues'] ?? null) ? $opsCheckResult['issues'] : [],
            ],
            'backup' => is_array($opsCheckResult['backup'] ?? null) ? $opsCheckResult['backup'] : [],
            'opsAlert' => $this->opsAlertSettings(),
        ];
    }

    private function opsCheckHistory(): array
    {
        $value = Setting::valueFor('ops_check', 'history', []);
        $history = is_array($value) ? $value : (json_decode((string) $value, true) ?: []);

        return array_slice(array_reverse($history), 0, 24);
    }

    private function opsAlertSettings(): array
    {
        return [
            'enabled' => $this->settingEnabled(Setting::valueFor('ops_alert', 'enabled', false)),
            'webhookUrl' => (string) Setting::valueFor('ops_alert', 'webhook_url', ''),
            'minIntervalMinutes' => (int) Setting::valueFor('ops_alert', 'min_interval_minutes', 60),
            'lastAlertedAt' => Setting::valueFor('ops_alert', 'last_alerted_at', null),
            'lastStatusCode' => Setting::valueFor('ops_alert', 'last_status_code', null),
            'lastError' => Setting::valueFor('ops_alert', 'last_error', null),
            'lastRecoveredAt' => Setting::valueFor('ops_alert', 'last_recovered_at', null),
            'lastRecoveryStatusCode' => Setting::valueFor('ops_alert', 'last_recovery_status_code', null),
            'lastRecoveryError' => Setting::valueFor('ops_alert', 'last_recovery_error', null),
            'lastTestedAt' => Setting::valueFor('ops_alert', 'last_tested_at', null),
            'lastTestStatusCode' => Setting::valueFor('ops_alert', 'last_test_status_code', null),
            'lastTestError' => Setting::valueFor('ops_alert', 'last_test_error', null),
        ];
    }

    private function settingEnabled(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    private function opsCheckResult(): array
    {
        $value = Setting::valueFor('ops_check', 'last_result', []);
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function auditLogs(array $filters)
    {
        return AuditLog::query()
            ->when(($filters['audit_action'] ?? '') !== '', fn (Builder $query) => $query->where('action', 'like', '%'.$filters['audit_action'].'%'))
            ->when(($filters['audit_target'] ?? '') !== '', function (Builder $query) use ($filters): void {
                $target = $filters['audit_target'];
                $query->where(function (Builder $query) use ($target): void {
                    $query->where('target_type', 'like', '%'.$target.'%');
                    if (ctype_digit($target)) {
                        $query->orWhere('target_id', (int) $target);
                    }
                });
            })
            ->when(($filters['audit_ip'] ?? '') !== '', fn (Builder $query) => $query->where('ip', 'like', '%'.$filters['audit_ip'].'%'))
            ->latest('created_at')
            ->limit(50)
            ->get();
    }

    private function backToFiles(string $message): RedirectResponse
    {
        return redirect()->route('admin-lite.dashboard', ['tab' => 'files'])->with('status', $message);
    }

    private function failedJobs(bool $actionableOnly = false): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')
            ->latest('failed_at')
            ->limit(10)
            ->get()
            ->map(function ($job): array {
                $payload = (string) $job->payload;
                $exception = (string) $job->exception;
                $fileId = $this->extractScanFileId($payload);
                $file = $fileId ? SharedFile::query()->find($fileId) : null;

                return [
                    'id' => $job->id,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'failed_at' => $job->failed_at,
                    'name' => $this->extractJobName($payload),
                    'file_id' => $fileId,
                    'file' => $file,
                    'actionable' => $file instanceof SharedFile && str_contains($payload, 'ScanUploadedFile'),
                    'summary' => $this->failedJobSummary($payload, $exception, $fileId, $file),
                    'exception' => mb_substr(trim(strtok($exception, "\n") ?: $exception), 0, 260),
                    'exception_details' => mb_substr(trim($exception), 0, 2000),
                    'payload_excerpt' => mb_substr(trim($payload), 0, 1000),
                ];
            })
            ->when($actionableOnly, fn ($jobs) => $jobs->filter(fn (array $job): bool => ! empty($job['actionable'])))
            ->values()
            ->all();
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
                $fileId = $this->extractScanFileId((string) $job->payload);

                return $fileId !== null && SharedFile::query()->whereKey($fileId)->exists();
            })
            ->count();
    }

    private function failedJobSummary(string $payload, string $exception, ?int $fileId, ?SharedFile $file): string
    {
        if ($fileId !== null && ! $file) {
            return '源文件已不存在，保留失败记录用于审计。';
        }
        if (str_contains($payload, 'ComputeFileHash') && str_contains($exception, 'Disk [netdisk123]')) {
            return '历史 123 网盘 disk 配置失败，当前已按外部转存配置收口。';
        }
        if (str_contains($payload, 'ScanUploadedFile') && str_contains($exception, 'MaxAttemptsExceededException')) {
            return '扫描任务超时，可在文件仍存在时重派发扫描。';
        }

        return '请查看异常摘要并按任务类型处理。';
    }

    private function suspiciousDownloads(): array
    {
        return FileDownload::query()
            ->select('ip', DB::raw('count(*) as total'), DB::raw("sum(case when failure_reason = 'risk_ack_confirmed' then 1 else 0 end) as risk_acks"), DB::raw('max(created_at) as last_at'))
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('ip')
            ->having('total', '>=', 20)
            ->orHaving('risk_acks', '>=', 3)
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'ip' => $row->ip,
                'total' => (int) $row->total,
                'risk_acks' => (int) $row->risk_acks,
                'last_at' => $row->last_at,
            ])
            ->all();
    }

    private function downloadRiskEvents(): array
    {
        return Setting::query()
            ->where('group', 'download_risk')
            ->latest('updated_at')
            ->limit(20)
            ->get(['value'])
            ->map(function (Setting $setting): array {
                $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);

                if (! is_array($decoded)) {
                    return [];
                }

                $decoded['status'] = $decoded['status'] ?? 'observing';
                if (empty($decoded['observed_until'])) {
                    try {
                        $decoded['observed_until'] = Carbon::parse((string) ($decoded['created_at'] ?? now()))->addDay()->toDateTimeString();
                    } catch (Throwable) {
                        $decoded['observed_until'] = now()->addDay()->toDateTimeString();
                    }
                }

                return $decoded;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function fileHasThreatType(SharedFile $file, string $type): bool
    {
        $type = mb_strtolower(trim($type));
        if ($type === '') {
            return true;
        }

        $details = is_array($file->malware_scan_details) ? $file->malware_scan_details : json_decode((string) $file->malware_scan_details, true);
        if (! is_array($details)) {
            return false;
        }

        foreach (array_merge($details['threats'] ?? [], $details['threat_types'] ?? []) as $threat) {
            if (str_contains(mb_strtolower((string) $threat), $type)) {
                return true;
            }
        }

        return str_contains(mb_strtolower(json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''), $type);
    }

    private function fileMatchesArchiveScanFilter(SharedFile $file, string $filter): bool
    {
        $details = is_array($file->malware_scan_details) ? $file->malware_scan_details : json_decode((string) $file->malware_scan_details, true);
        if (! is_array($details)) {
            return false;
        }

        $archiveScan = $details['details']['archive_scan'] ?? null;
        if (! is_array($archiveScan)) {
            return false;
        }

        $files = is_array($archiveScan['files'] ?? null) ? $archiveScan['files'] : [];

        return match ($filter) {
            'partial' => (float) ($archiveScan['coverage_percent'] ?? 0) < 100 || (int) ($archiveScan['skipped_files'] ?? 0) > 0,
            'skipped' => (int) ($archiveScan['skipped_files'] ?? 0) > 0,
            'media' => collect($files)->contains(fn ($entry): bool => is_array($entry) && ($entry['entry_type'] ?? null) === 'media'),
            'ai_failed' => str_contains(json_encode($archiveScan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'media_review_failed')
                || str_contains(json_encode($archiveScan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '', 'media_review_required'),
            default => true,
        };
    }

    private function userNotices(): array
    {
        return Setting::query()
            ->where('group', 'user_notice')
            ->latest('updated_at')
            ->limit(20)
            ->get(['value'])
            ->map(function (Setting $setting): array {
                $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);

                return is_array($decoded) ? $decoded : [];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function riskInsights(array $riskReviewStates, array $riskAppeals): array
    {
        $riskFiles = SharedFile::query()->where('malware_scan_passed', false)->whereNotNull('malware_scan_checked_at')->latest('malware_scan_checked_at')->limit(300)->get();
        $typeCounts = [];
        $ipCounts = [];
        $extensionCounts = [];
        $hashCounts = [];
        foreach ($riskFiles as $file) {
            $details = is_array($file->malware_scan_details) ? $file->malware_scan_details : json_decode((string) $file->malware_scan_details, true);
            foreach (array_unique(array_filter(array_merge($details['threats'] ?? [], $details['threat_types'] ?? []))) as $type) {
                $type = trim((string) $type);
                if ($type !== '') {
                    $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
                }
            }
            $ip = trim((string) $file->uploader_ip);
            if ($ip !== '') {
                $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
            }
            $extension = strtolower(trim((string) ($file->extension ?: pathinfo((string) $file->original_name, PATHINFO_EXTENSION))));
            if ($extension !== '') {
                $extensionCounts[$extension] = ($extensionCounts[$extension] ?? 0) + 1;
            }
            $sha256 = trim((string) ($file->sha256 ?? ''));
            if ($sha256 !== '') {
                $hashCounts[$sha256] = ($hashCounts[$sha256] ?? 0) + 1;
            }
        }
        arsort($typeCounts);
        arsort($ipCounts);
        arsort($extensionCounts);
        $duplicateHashes = array_filter($hashCounts, fn (int $count): bool => $count >= 2);
        arsort($duplicateHashes);

        $scanned24h = SharedFile::query()->whereNotNull('malware_scan_checked_at')->where('malware_scan_checked_at', '>=', now()->subDay())->count();
        $threat24h = SharedFile::query()->where('malware_scan_passed', false)->whereNotNull('malware_scan_checked_at')->where('malware_scan_checked_at', '>=', now()->subDay())->count();
        $appealTotal = array_sum(array_map(fn (array $appeal): int => (int) ($appeal['count'] ?? 0), $riskAppeals));
        $falsePositiveCount = collect($riskReviewStates)->filter(fn (array $state): bool => ($state['status'] ?? '') === 'false_positive')->count();

        return [
            'scanned24h' => $scanned24h,
            'threat24h' => $threat24h,
            'threatRate24h' => $scanned24h > 0 ? round($threat24h / $scanned24h * 100, 1) : 0,
            'appealTotal' => $appealTotal,
            'falsePositiveCount' => $falsePositiveCount,
            'appealPassRate' => $appealTotal > 0 ? round($falsePositiveCount / $appealTotal * 100, 1) : 0,
            'topThreatTypes' => array_slice($typeCounts, 0, 8, true),
            'topUploaderIps' => array_slice($ipCounts, 0, 8, true),
            'topExtensions' => array_slice($extensionCounts, 0, 8, true),
            'duplicateHashes' => array_slice($duplicateHashes, 0, 8, true),
        ];
    }

    private function batchShares()
    {
        return Setting::query()
            ->where('group', 'batch_share')
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->map(function (Setting $setting): array {
                $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);
                $decoded = is_array($decoded) ? $decoded : [];
                $codes = is_array($decoded['codes'] ?? null) ? $decoded['codes'] : [];
                $files = SharedFile::query()->whereIn('code', $codes)->get(['code', 'download_count']);

                return [
                    'id' => $setting->id,
                    'token' => $setting->key,
                    'title' => (string) ($decoded['title'] ?? ''),
                    'count' => count($codes),
                    'downloads' => (int) $files->sum('download_count'),
                    'created_at' => (string) ($decoded['created_at'] ?? ''),
                    'expires_at' => (string) ($decoded['expires_at'] ?? ''),
                    'ip' => (string) ($decoded['ip'] ?? ''),
                    'closed' => ! empty($decoded['closed_at']),
                ];
            });
    }

    private function writeUserNotice(string $code, string $type, string $message, ?string $note = null): void
    {
        Setting::query()->create([
            'group' => 'user_notice',
            'key' => strtoupper($code).':'.now()->format('YmdHis').':'.substr(hash('sha256', $type.'|'.$message), 0, 8),
            'value' => json_encode([
                'code' => strtoupper($code),
                'type' => $type,
                'message' => $message,
                'note' => trim((string) $note),
                'created_at' => now()->toDateTimeString(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'type' => 'json',
        ]);
    }

    private function extractJobName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        if (is_array($decoded) && is_string($decoded['displayName'] ?? null)) {
            return $decoded['displayName'];
        }
        if (preg_match('/"displayName";s:\d+:"([^"]+)"/', $payload, $matches) === 1) {
            return $matches[1];
        }
        if (str_contains($payload, 'ScanUploadedFile')) {
            return 'App\\Jobs\\ScanUploadedFile';
        }
        if (str_contains($payload, 'ComputeFileHash')) {
            return 'App\\Jobs\\ComputeFileHash';
        }

        return '未知任务';
    }

    private function extractScanFileId(string $payload): ?int
    {
        if (! str_contains($payload, 'ScanUploadedFile')) {
            return null;
        }
        if (preg_match('/fileId";i:(\d+)/', $payload, $matches) === 1) {
            return (int) $matches[1];
        }
        if (preg_match('/fileId.*?i:(\d+)/s', $payload, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function ensureCanManageAdmins(Request $request): void
    {
        abort_unless($this->canManageAdmins($request), 403);
    }

    private function adminPasswordMatches(string $password): bool
    {
        $hash = $this->adminPasswordHash();

        if ($hash) {
            return Hash::check($password, $hash);
        }

        $envPassword = env('ADMIN_PASSWORD');

        return is_string($envPassword) && $envPassword !== '' && hash_equals($envPassword, $password);
    }

    private function adminPasswordHash(): ?string
    {
        try {
            $hash = Setting::valueFor('admin', 'password_hash');
        } catch (Throwable) {
            return null;
        }

        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    private function sevenDayTrends(): array
    {
        return collect(range(6, 0))->map(function (int $daysAgo): array {
            $date = Carbon::today()->subDays($daysAgo);

            return [
                'date' => $date->format('m-d'),
                'uploads' => FileUpload::query()->whereDate('created_at', $date)->where('success', true)->count(),
                'downloads' => FileDownload::query()->whereDate('created_at', $date)->where('success', true)->count(),
            ];
        })->all();
    }

    public static function defaultTermsContent(): string
    {
        return <<<'HTML'
<h2>用户服务协议</h2>
<p>叶宇文件快递提供临时文件上传、分享和下载能力。你开始使用本服务，就表示已经阅读并同意按本协议使用它。</p>
<h3>一、这项服务提供什么</h3>
<p>你可以上传文件，生成短链接或提取码，其他人再通过链接或提取码下载。服务只用于临时存储和分享，不承诺长期保存文件内容。</p>
<h3>二、上传内容规则</h3>
<div class="warn-box danger">
  <div>
    <p><strong>以下内容禁止上传：</strong></p>
    <ul>
      <li>违反中华人民共和国法律法规的任何内容</li>
      <li>涉及国家安全、国家机密的文件</li>
      <li>淫秽、色情、赌博、暴力、恐怖内容</li>
      <li>侵犯他人知识产权、商业秘密的文件</li>
      <li>含有病毒、木马、恶意代码的文件</li>
      <li>侵犯他人隐私、个人信息的内容</li>
      <li>散布谣言、虚假信息的内容</li>
      <li>其他违反公序良俗的内容</li>
    </ul>
  </div>
</div>
<div class="warn-box success">
  <div>
    <p><strong>以下内容可以正常上传：</strong></p>
    <ul>
      <li>个人合法文档、图片、视频等</li>
      <li>工作学习相关的资料文件</li>
      <li>开源软件、公开资源</li>
      <li>其他不违反法律法规的合法内容</li>
    </ul>
  </div>
</div>
<h3>三、使用限制</h3>
<ul>
  <li>单个文件大小限制：根据系统配置</li>
  <li>文件保存期限：根据用户选择</li>
  <li>过期文件会自动删除，删除后不可恢复</li>
</ul>
<h3>四、违规处理</h3>
<p>发现违规内容后，我们有权采取以下措施：立即删除违规文件、封禁违规用户的 IP 地址或设备、配合有关部门进行调查、保留追究法律责任的权利。</p>
<h3>五、责任说明</h3>
<ul>
  <li>本服务按"现状"提供，不对可用性、稳定性作额外保证</li>
  <li>你需要对自己上传的文件内容承担全部法律责任</li>
  <li>因不可抗力导致的服务中断或数据丢失，本服务不承担责任</li>
</ul>
<h3>六、协议更新</h3>
<p>本协议如有调整，会直接更新在本页面。你继续使用本服务，就视为接受更新后的协议内容。</p>
HTML;
    }

    public static function defaultPrivacyContent(): string
    {
        return <<<'HTML'
<h2>隐私政策</h2>
<p>叶宇文件快递尊重并保护你的隐私。本政策说明我们如何收集、使用和保护你的信息。</p>
<h3>一、我们收集什么信息</h3>
<ul>
  <li>上传的文件内容（临时存储，到期自动删除）</li>
  <li>IP 地址（用于安全防护和滥用检测）</li>
  <li>设备和浏览器信息（用于兼容性优化）</li>
</ul>
<h3>二、信息如何使用</h3>
<ul>
  <li>提供文件上传、存储和下载服务</li>
  <li>防范滥用和安全威胁</li>
  <li>改善服务质量</li>
</ul>
<h3>三、信息保护</h3>
<p>我们采用合理的安全措施保护你的信息。文件在过期后会自动从服务器删除。</p>
<h3>四、信息共享</h3>
<p>我们不会将你的个人信息出售或分享给第三方，除非法律法规要求。</p>
<h3>五、Cookie</h3>
<p>本服务可能使用 Cookie 或类似技术来维持会话和改善体验。</p>
<h3>六、政策更新</h3>
<p>本政策如有更新，会发布在本页面。继续使用本服务即视为同意更新后的政策。</p>
HTML;
    }
}
