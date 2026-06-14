<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ComputeFileHash;
use App\Jobs\ScanUploadedFile;
use App\Models\BlockedIp;
use App\Models\FileDownload;
use App\Models\FileUpload;
use App\Models\RiskDownloadAckLog;
use App\Models\SharedFile;
use App\Models\Setting;
use App\Support\ApiEnvelope;
use App\Support\YeyuFileExpressSettings;
use App\Support\RiskEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
class FileController extends Controller
{
    private RiskEvaluator $riskEvaluator;

    public function __construct(RiskEvaluator $riskEvaluator)
    {
        $this->riskEvaluator = $riskEvaluator;
    }

    public function store(Request $request): JsonResponse
    {
        if (BlockedIp::matches($request->ip(), 'upload')) {
            return $this->recordFailedUpload($request, '当前 IP 暂不可上传', 403);
        }

        $uploaded = $request->file('file');
        if (! $uploaded instanceof UploadedFile || ! $uploaded->isValid()) {
            return $this->recordFailedUpload($request, '请选择要上传的文件', 422);
        }

        $uploadConfig = YeyuFileExpressSettings::upload();
        $maxSize = (int) $uploadConfig['maxFileSize'];
        if ($uploaded->getSize() > $maxSize) {
            return $this->recordFailedUpload($request, '文件超过最大上传限制', 413, $uploaded);
        }

        $originalName = $this->sanitizeFileName($uploaded->getClientOriginalName());
        $extension = strtolower($uploaded->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedTypes = $this->allowedTypes((string) $uploadConfig['allowedFileTypes']);
        if ($allowedTypes !== [] && $extension !== '' && ! in_array($extension, $allowedTypes, true)) {
            return $this->recordFailedUpload($request, '文件类型不允许上传', 422, $uploaded);
        }

        $geetest = YeyuFileExpressSettings::geetest();
        if ($geetest['enabled'] && ! $this->captchaLooksPresent($request)) {
            return $this->recordFailedUpload($request, '请先完成人机验证', 422, $uploaded);
        }

        $risk = $this->riskEvaluator->evaluateUpload(
            $request,
            $originalName,
            $extension,
            $uploaded->getClientMimeType() ?: $uploaded->getMimeType(),
            (int) $uploaded->getSize(),
        );
        if ($risk['blocked']) {
            return $this->recordFailedUpload($request, '上传触发风控拦截', 403, $uploaded);
        }

        $expireDays = $this->expireDays($request, (int) $uploadConfig['defaultExpireDays'], (int) $uploadConfig['maxExpireDays']);
        $extractCode = strtoupper(trim((string) $this->firstInput($request, ['extractCode', 'extract_code', 'code', 'password'])));
        if ($extractCode !== '' && (mb_strlen($extractCode) < 4 || mb_strlen($extractCode) > 6)) {
            return $this->recordFailedUpload($request, '提取码需为 4-6 位', 422, $uploaded);
        }

        $uploadedHash = hash_file('sha256', $uploaded->getRealPath()) ?: null;
        $duplicate = $uploadedHash ? SharedFile::query()
            ->where('sha256', $uploadedHash)
            ->whereNotNull('path')
            ->latest('id')
            ->first() : null;
        if ($duplicate && ! Storage::disk($duplicate->disk)->exists($duplicate->path)) {
            $duplicate = null;
        }
        if ($duplicate && (bool) $duplicate->malware_scan_checked_at && ! (bool) $duplicate->malware_scan_passed && $this->riskReviewStatus($duplicate) === 'confirmed') {
            return $this->recordFailedUpload($request, '该文件指纹已被确认违规，禁止重复上传', 403, $uploaded);
        }

        $disk = $duplicate?->disk ?: config('filesystems.default', 'local');
        $code = $this->generateCode();
        $storedName = $duplicate?->stored_name ?: (string) Str::uuid().($extension ? ".{$extension}" : '');
        $path = $duplicate?->path ?: 'uploads/'.now()->format('Y/m/d').'/'.$storedName;

        if (! $duplicate) {
            $stream = fopen($uploaded->getRealPath(), 'rb');
            Storage::disk($disk)->put($path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $file = SharedFile::query()->create([
            'code' => $code,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $uploaded->getClientMimeType() ?: $uploaded->getMimeType() ?: 'application/octet-stream',
            'extension' => $extension ?: null,
            'size' => $uploaded->getSize(),
            'sha256' => $uploadedHash,
            'scan_status' => $duplicate?->scan_status ?: 'pending',
            'scan_result' => $duplicate?->scan_result,
            'risk_score' => $risk['score'],
            'risk_reasons_json' => $risk['reasons'],
            'extract_code_hash' => $extractCode !== '' ? Hash::make($extractCode) : null,
            'has_extract_code' => $extractCode !== '',
            'share_theme' => (string) ($this->firstInput($request, ['shareTheme', 'share_theme', 'theme']) ?: 'default'),
            'status' => 'active',
            'expires_at' => now()->addDays($expireDays),
            'uploaded_at' => now(),
            'uploader_ip' => $request->ip(),
            'uploader_user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'malware_scan_passed' => $duplicate ? (bool) $duplicate->malware_scan_passed : false,
            'malware_scan_checked_at' => $duplicate?->malware_scan_checked_at,
            'malware_scan_details' => $duplicate?->malware_scan_details,
        ]);

        if (! $uploadedHash) {
            ComputeFileHash::dispatch($file->id);
        }
        if (! $duplicate || ! $duplicate->malware_scan_checked_at) {
            ScanUploadedFile::dispatch($file->id);
        }

        FileUpload::query()->create([
            'file_id' => $file->id,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'original_name' => $originalName,
            'size' => $file->size,
            'mime_type' => $file->mime_type,
            'success' => true,
            'created_at' => now(),
        ]);

        $ownerToken = Str::random(40);
        Setting::query()->create([
            'group' => 'file_owner',
            'key' => $file->code,
            'value' => Hash::make($ownerToken),
            'type' => 'string',
        ]);

        return ApiEnvelope::ok($this->publicFile($file) + ['ownerToken' => $ownerToken], '上传成功');
    }

    public function show(string $code): JsonResponse
    {
        $file = $this->findPublicFile($code);
        if (! $file) {
            return ApiEnvelope::error('文件不存在或已过期', 404, 404);
        }
        if ($file->status === 'blocked') {
            return ApiEnvelope::error('文件已被封禁', 403, 403);
        }

        return ApiEnvelope::ok($this->publicFile($file), '获取文件成功');
    }

    public function scanStatus(string $code): JsonResponse
    {
        $file = $this->findPublicFile($code);
        if (! $file) {
            return ApiEnvelope::error('文件不存在或已过期', 404, 404);
        }

        $malwareStatus = 'pending';
        if ($file->malware_scan_checked_at) {
            $malwareStatus = $file->malware_scan_passed ? 'clean' : 'threat';
        }

        return ApiEnvelope::ok([
            'code' => $file->code,
            'scanStatus' => $file->scan_status,
            'malwareStatus' => $malwareStatus,
            'riskScore' => (int) ($file->risk_score ?? 0),
            'riskReasons' => $file->risk_reasons_json ?: [],
            'checkedAt' => $file->malware_scan_checked_at ? strtotime((string) $file->malware_scan_checked_at) * 1000 : null,
            'threatDetailsUrl' => route('files.threat-details', ['code' => $file->code], false),
            'downloadUrl' => route('api.files.download', ['code' => $file->code], false),
        ], '获取扫描状态成功');
    }

    public function download(Request $request, string $code)
    {
        $normalized = strtoupper($code);
        $file = SharedFile::query()->where('code', $normalized)->first();

        if (! $file || $file->isExpired() || $file->status === 'deleted') {
            $this->recordDownload($file, $request, $normalized, false, 'not_found');

            return response('文件不存在或已过期', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($file->status === 'blocked' || BlockedIp::matches($request->ip(), 'download')) {
            $this->recordDownload($file, $request, $normalized, false, 'blocked');

            return response('文件已被封禁', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($this->downloadRateLimited($request, $file)) {
            $this->recordDownload($file, $request, $normalized, false, 'download_rate_limited');

            return response('下载过于频繁，请稍后再试', 429, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if (!in_array($file->scan_status, ['clean', 'skipped'])) {
            $this->recordDownload($file, $request, $normalized, false, 'scan_not_passed');

            if ($file->scan_status === 'pending') {
                return response('文件正在病毒扫描中，请稍后再试', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
            }

            return response('文件病毒扫描未通过，已被拦截', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($this->isHardBlockedRisk($file)) {
            $this->recordDownload($file, $request, $normalized, false, 'content_policy_blocked');

            return response('该文件已被识别为违规内容，禁止下载', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $riskAcknowledged = $this->requiresRiskAcknowledgement($file) && $this->hasValidRiskAcknowledgement($request, $file);
        if ($this->requiresRiskAcknowledgement($file) && ! $riskAcknowledged) {
            $this->recordDownload($file, $request, $normalized, false, 'risk_ack_required');

            return response('该文件已检测到安全风险，请先在威胁详情页阅读免责申明并确认后再下载', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $extractCode = strtoupper(trim((string) ($request->query('extractCode') ?: $request->query('code'))));
        if ($file->has_extract_code && (! $file->extract_code_hash || ! Hash::check($extractCode, $file->extract_code_hash))) {
            $this->recordDownload($file, $request, $normalized, false, 'bad_extract_code');

            return response()->view('files.extract-code-error', ['code' => $normalized], 403);
        }

        if (! Storage::disk($file->disk)->exists($file->path)) {
            $this->recordDownload($file, $request, $normalized, false, 'missing_storage_file');

            return response('文件不存在或已过期', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        SharedFile::query()
            ->whereKey($file->id)
            ->update([
                'download_count' => DB::raw('download_count + 1'),
                'download_bytes' => DB::raw('download_bytes + '.(int) $file->size),
                'last_downloaded_at' => now(),
                'updated_at' => now(),
            ]);
        $this->recordDownload($file, $request, $normalized, true, $riskAcknowledged ? 'risk_ack_confirmed' : null, $file->size);
        if ($riskAcknowledged) {
            $this->recordRiskAcknowledgement($file, $request, $normalized);
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);

        return response()->streamDownload(function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, $file->original_name, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Length' => (string) $file->size,
            'Cache-Control' => 'no-store',
        ]);
    }

    public function preview(Request $request, string $code)
    {
        $normalized = strtoupper($code);
        $file = SharedFile::query()->where('code', $normalized)->first();

        if (! $file || $file->isExpired() || $file->status === 'deleted') {
            $this->recordDownload($file, $request, $normalized, false, 'preview_not_found');

            return response('文件不存在或已过期', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($file->status === 'blocked' || BlockedIp::matches($request->ip(), 'download')) {
            $this->recordDownload($file, $request, $normalized, false, 'preview_blocked');

            return response('文件已被封禁', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($this->downloadRateLimited($request, $file)) {
            $this->recordDownload($file, $request, $normalized, false, 'preview_rate_limited');

            return response('访问过于频繁，请稍后再试', 429, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if (! in_array($file->scan_status, ['clean', 'skipped'], true)) {
            $this->recordDownload($file, $request, $normalized, false, 'preview_scan_not_passed');

            return response('文件尚未通过安全扫描，暂不能预览', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($this->isHardBlockedRisk($file)) {
            $this->recordDownload($file, $request, $normalized, false, 'preview_content_policy_blocked');

            return response('该文件已被识别为违规内容，禁止预览', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $riskAcknowledged = $this->requiresRiskAcknowledgement($file) && $this->hasValidRiskAcknowledgement($request, $file);
        if ($this->requiresRiskAcknowledgement($file) && ! $riskAcknowledged) {
            $this->recordDownload($file, $request, $normalized, false, 'preview_risk_ack_required');

            return response('该文件已检测到安全风险，请先在威胁详情页确认后再预览', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $extractCode = strtoupper(trim((string) ($request->query('extractCode') ?: $request->query('code'))));
        if ($file->has_extract_code && (! $file->extract_code_hash || ! Hash::check($extractCode, $file->extract_code_hash))) {
            $this->recordDownload($file, $request, $normalized, false, 'preview_bad_extract_code');

            return response()->view('files.extract-code-error', ['code' => $normalized], 403);
        }

        $previewMime = $this->previewMimeType($file);
        if ($previewMime === null) {
            $this->recordDownload($file, $request, $normalized, false, 'preview_unsupported_type');

            return response('此文件类型暂不支持在线预览，请下载后查看', 415, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if (! Storage::disk($file->disk)->exists($file->path)) {
            $this->recordDownload($file, $request, $normalized, false, 'preview_missing_storage_file');

            return response('文件不存在或已过期', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);
        $this->recordDownload($file, $request, $normalized, true, $riskAcknowledged ? 'preview_risk_ack_confirmed' : 'preview', 0);

        return response()->stream(function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $previewMime,
            'Content-Length' => (string) $file->size,
            'Content-Disposition' => 'inline; filename="'.$this->safeHeaderFilename($file->original_name).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function historySync(Request $request): JsonResponse
    {
        $codes = collect($request->input('codes', []))
            ->filter()
            ->map(fn ($code): string => strtoupper((string) $code))
            ->filter(fn (string $code): bool => preg_match('/^[A-Z0-9]{4,12}$/', $code) === 1)
            ->unique()
            ->take(100)
            ->values();

        $files = SharedFile::query()->whereIn('code', $codes)->get()->keyBy('code');
        $list = $codes->map(function (string $code) use ($files): array {
            $file = $files->get($code);
            if (! $file) {
                return ['code' => $code, 'exists' => false, 'status' => 'deleted'];
            }

            return [
                'code' => $code,
                'exists' => ! $file->isExpired() && $file->status === 'active',
                'status' => $file->publicStatus(),
                'originalName' => $file->original_name,
                'size' => $file->size,
                'mimeType' => $file->mime_type,
                'hasExtractCode' => $file->has_extract_code,
                'expiresAt' => $file->expires_at ? $file->expires_at->valueOf() : null,
                'uploadedAt' => $file->uploaded_at ? $file->uploaded_at->valueOf() : null,
                'downloadUrl' => route('api.files.download', ['code' => $file->code], false),
            ];
        })->values();

        return ApiEnvelope::ok(['list' => $list], '同步成功');
    }

    public function batchShare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'expiresDays' => ['nullable', 'integer', 'min:1', 'max:30'],
            'coverCode' => ['nullable', 'string', 'max:12'],
        ]);
        $codes = collect($request->input('codes', []))
            ->filter()
            ->map(fn ($code): string => strtoupper((string) $code))
            ->filter(fn (string $code): bool => preg_match('/^[A-Z0-9]{4,12}$/', $code) === 1)
            ->unique()
            ->take(50)
            ->values();

        if ($codes->count() < 2) {
            return ApiEnvelope::error('至少选择 2 个有效分享码', 422, 422);
        }

        $files = SharedFile::query()
            ->whereIn('code', $codes)
            ->where('status', 'active')
            ->get()
            ->filter(fn (SharedFile $file): bool => ! $file->isExpired())
            ->values();

        if ($files->count() < 2) {
            return ApiEnvelope::error('可分享文件不足 2 个', 422, 422);
        }

        $token = Str::random(16);
        $manageToken = Str::random(40);
        $expiresDays = (int) ($data['expiresDays'] ?? 7);
        $expiresAt = now()->addDays($expiresDays);
        Setting::query()->create([
            'group' => 'batch_share',
            'key' => $token,
            'value' => json_encode([
                'token' => $token,
                'title' => trim((string) ($data['title'] ?? '')),
                'description' => trim((string) ($data['description'] ?? '')),
                'codes' => $files->pluck('code')->values()->all(),
                'cover_code' => $files->pluck('code')->contains(strtoupper((string) ($data['coverCode'] ?? ''))) ? strtoupper((string) $data['coverCode']) : null,
                'created_at' => now()->toDateTimeString(),
                'expires_at' => $expiresAt->toDateTimeString(),
                'manage_token_hash' => Hash::make($manageToken),
                'ip' => $request->ip(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'type' => 'json',
        ]);

        return ApiEnvelope::ok([
            'token' => $token,
            'manageToken' => $manageToken,
            'url' => route('files.batch', ['token' => $token], false),
            'count' => $files->count(),
            'expiresAt' => $expiresAt->valueOf(),
        ], '批量分享已生成');
    }

    public function ownerBatchShares(Request $request): JsonResponse
    {
        $items = collect($request->input('shares', []))->filter()->take(50);
        $shares = [];
        foreach ($items as $item) {
            $token = (string) ($item['token'] ?? '');
            $manageToken = (string) ($item['manageToken'] ?? '');
            $setting = $this->ownedBatchShare($token, $manageToken);
            if (! $setting) {
                continue;
            }
            $decoded = $this->decodeSettingValue($setting);
            $codes = is_array($decoded['codes'] ?? null) ? $decoded['codes'] : [];
            $shares[] = [
                'token' => $setting->key,
                'title' => (string) ($decoded['title'] ?? ''),
                'description' => (string) ($decoded['description'] ?? ''),
                'count' => count($codes),
                'createdAt' => isset($decoded['created_at']) ? strtotime((string) $decoded['created_at']) * 1000 : null,
                'expiresAt' => isset($decoded['expires_at']) ? strtotime((string) $decoded['expires_at']) * 1000 : null,
                'closed' => ! empty($decoded['closed_at']),
                'url' => route('files.batch', ['token' => $setting->key], false),
            ];
        }

        return ApiEnvelope::ok(['shares' => $shares], '批量分享已同步');
    }

    public function ownerBatchShareUpdate(Request $request, string $token): JsonResponse
    {
        $data = $request->validate([
            'manageToken' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $setting = $this->ownedBatchShare($token, (string) $data['manageToken']);
        if (! $setting) {
            return ApiEnvelope::error('批量分享不存在或管理凭证无效', 403, 403);
        }
        $decoded = $this->decodeSettingValue($setting);
        $decoded['title'] = trim((string) ($data['title'] ?? ''));
        $decoded['description'] = trim((string) ($data['description'] ?? ''));
        $this->saveBatchShare($setting, $decoded);

        return ApiEnvelope::ok(['token' => $setting->key], '批量分享信息已更新');
    }

    public function ownerBatchShareExtend(Request $request, string $token): JsonResponse
    {
        $manageToken = (string) $request->input('manageToken', '');
        $setting = $this->ownedBatchShare($token, $manageToken);
        if (! $setting) {
            return ApiEnvelope::error('批量分享不存在或管理凭证无效', 403, 403);
        }
        $days = max(1, min(30, (int) $request->input('days', 7)));
        $decoded = $this->decodeSettingValue($setting);
        $base = strtotime((string) ($decoded['expires_at'] ?? '')) > time() ? strtotime((string) $decoded['expires_at']) : time();
        $decoded['expires_at'] = date('Y-m-d H:i:s', $base + $days * 86400);
        unset($decoded['closed_at']);
        $this->saveBatchShare($setting, $decoded);

        return ApiEnvelope::ok(['expiresAt' => strtotime($decoded['expires_at']) * 1000], '批量分享已续期');
    }

    public function ownerBatchShareClose(Request $request, string $token): JsonResponse
    {
        $manageToken = (string) $request->input('manageToken', '');
        $setting = $this->ownedBatchShare($token, $manageToken);
        if (! $setting) {
            return ApiEnvelope::error('批量分享不存在或管理凭证无效', 403, 403);
        }
        $decoded = $this->decodeSettingValue($setting);
        $decoded['closed_at'] = now()->toDateTimeString();
        $this->saveBatchShare($setting, $decoded);

        return ApiEnvelope::ok(['token' => $setting->key], '批量分享已关闭');
    }

    public function ownerExtend(Request $request, string $code): JsonResponse
    {
        $file = $this->ownerFile($request, $code);
        if (! $file) {
            return ApiEnvelope::error('文件不存在或管理凭证无效', 403, 403);
        }

        $days = max(1, min(30, (int) $request->input('days', 7)));
        $base = $file->expires_at && $file->expires_at->isFuture() ? $file->expires_at : now();
        $file->forceFill(['expires_at' => $base->copy()->addDays($days)])->save();

        return ApiEnvelope::ok($this->publicFile($file->refresh()), '有效期已延长');
    }

    public function ownerExtractCode(Request $request, string $code): JsonResponse
    {
        $file = $this->ownerFile($request, $code);
        if (! $file) {
            return ApiEnvelope::error('文件不存在或管理凭证无效', 403, 403);
        }

        $extractCode = strtoupper(trim((string) $request->input('extractCode', '')));
        if ($extractCode !== '' && (mb_strlen($extractCode) < 4 || mb_strlen($extractCode) > 6)) {
            return ApiEnvelope::error('提取码需为 4-6 位', 422, 422);
        }

        $file->forceFill([
            'extract_code_hash' => $extractCode !== '' ? Hash::make($extractCode) : null,
            'has_extract_code' => $extractCode !== '',
        ])->save();

        return ApiEnvelope::ok($this->publicFile($file->refresh()), $extractCode !== '' ? '提取码已更新' : '提取码已关闭');
    }

    public function ownerMeta(Request $request, string $code): JsonResponse
    {
        $file = $this->ownerFile($request, $code);
        if (! $file) {
            return ApiEnvelope::error('文件不存在或管理凭证无效', 403, 403);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:240'],
        ]);
        $note = trim((string) ($data['note'] ?? ''));

        Setting::query()->updateOrCreate(
            ['group' => 'file_meta', 'key' => $file->code],
            ['value' => json_encode(['note' => $note, 'updated_at' => now()->toDateTimeString()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'type' => 'json']
        );

        return ApiEnvelope::ok($this->publicFile($file->refresh()), $note !== '' ? '文件备注已保存' : '文件备注已清空');
    }

    public function ownerDelete(Request $request, string $code): JsonResponse
    {
        $file = $this->ownerFile($request, $code);
        if (! $file) {
            return ApiEnvelope::error('文件不存在或管理凭证无效', 403, 403);
        }

        $file->forceFill(['status' => 'deleted'])->save();

        return ApiEnvelope::ok(['code' => $file->code, 'status' => 'deleted'], '文件分享已删除');
    }

    public function ownerBatchExtend(Request $request): JsonResponse
    {
        $items = collect($request->input('files', []))->filter()->take(50);
        $days = max(1, min(30, (int) $request->input('days', 7)));
        $updated = [];

        foreach ($items as $item) {
            $code = strtoupper((string) ($item['code'] ?? ''));
            $token = (string) ($item['ownerToken'] ?? '');
            $file = $this->ownerFileByToken($code, $token);
            if (! $file) {
                continue;
            }
            $base = $file->expires_at && $file->expires_at->isFuture() ? $file->expires_at : now();
            $file->forceFill(['expires_at' => $base->copy()->addDays($days)])->save();
            $updated[] = $file->code;
        }

        return ApiEnvelope::ok(['updated' => $updated, 'count' => count($updated)], '批量续期完成');
    }

    public function ownerBatchExtractCode(Request $request): JsonResponse
    {
        $items = collect($request->input('files', []))->filter()->take(50);
        $extractCode = strtoupper(trim((string) $request->input('extractCode', '')));
        if ($extractCode !== '' && (mb_strlen($extractCode) < 4 || mb_strlen($extractCode) > 6)) {
            return ApiEnvelope::error('提取码需为 4-6 位', 422, 422);
        }

        $updated = [];
        foreach ($items as $item) {
            $code = strtoupper((string) ($item['code'] ?? ''));
            $token = (string) ($item['ownerToken'] ?? '');
            $file = $this->ownerFileByToken($code, $token);
            if (! $file) {
                continue;
            }
            $file->forceFill([
                'extract_code_hash' => $extractCode !== '' ? Hash::make($extractCode) : null,
                'has_extract_code' => $extractCode !== '',
            ])->save();
            $updated[] = $file->code;
        }

        return ApiEnvelope::ok(['updated' => $updated, 'count' => count($updated)], $extractCode !== '' ? '批量提取码已更新' : '批量提取码已关闭');
    }

    public function thumbnail(Request $request, string $code)
    {
        $file = $this->findPublicFile($code);
        if (! $file || $file->status !== 'active' || $this->isHardBlockedRisk($file) || $this->requiresRiskAcknowledgement($file)) {
            return response('缩略图不可用', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $mime = strtolower((string) ($file->mime_type ?: ''));
        if (! str_starts_with($mime, 'image/')) {
            return response('缩略图暂只支持图片', 415, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        if (! Storage::disk($file->disk)->exists($file->path)) {
            return response('文件不存在', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $stream = Storage::disk($file->disk)->readStream($file->path);

        return response()->stream(function () use ($stream): void {
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function findPublicFile(string $code): ?SharedFile
    {
        $file = SharedFile::query()->where('code', strtoupper($code))->first();

        if (! $file || $file->isExpired() || ! in_array($file->status, ['active', 'blocked'], true)) {
            return null;
        }

        return $file;
    }

    private function publicFile(SharedFile $file): array
    {
        return [
            'code' => $file->code,
            'originalName' => $file->original_name,
            'size' => $file->size,
            'mimeType' => $file->mime_type,
            'hasExtractCode' => $file->has_extract_code,
            'expiresAt' => $file->expires_at ? $file->expires_at->valueOf() : null,
            'uploadedAt' => $file->uploaded_at ? $file->uploaded_at->valueOf() : null,
            'status' => $file->publicStatus(),
            'scanStatus' => $file->scan_status,
            'downloadCount' => (int) $file->download_count,
            'lastDownloadedAt' => $file->last_downloaded_at ? $file->last_downloaded_at->valueOf() : null,
            'downloads24h' => $downloads24h = FileDownload::query()->where('file_id', $file->id)->where('success', true)->where('created_at', '>=', now()->subDay())->count(),
            'spreadWarning' => $downloads24h >= 20,
            'daysUntilExpiry' => $file->expires_at ? now()->diffInDays($file->expires_at, false) : null,
            'downloadUrl' => route('api.files.download', ['code' => $file->code], false),
            'thumbnailUrl' => route('api.files.thumbnail', ['code' => $file->code], false),
            'note' => (string) ($this->fileMeta($file->code)['note'] ?? ''),
        ];
    }

    private function fileMeta(string $code): array
    {
        $setting = Setting::query()->where('group', 'file_meta')->where('key', strtoupper($code))->latest('updated_at')->first();
        if (! $setting) {
            return [];
        }

        $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function ownedBatchShare(string $token, string $manageToken): ?Setting
    {
        if (! preg_match('/^[A-Za-z0-9]{10,32}$/', $token) || $manageToken === '') {
            return null;
        }
        $setting = Setting::query()->where('group', 'batch_share')->where('key', $token)->latest('updated_at')->first();
        if (! $setting) {
            return null;
        }
        $decoded = $this->decodeSettingValue($setting);
        $hash = (string) ($decoded['manage_token_hash'] ?? '');

        return $hash !== '' && Hash::check($manageToken, $hash) ? $setting : null;
    }

    private function decodeSettingValue(Setting $setting): array
    {
        $decoded = is_array($setting->value) ? $setting->value : json_decode((string) $setting->value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function saveBatchShare(Setting $setting, array $decoded): void
    {
        $setting->forceFill([
            'value' => json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'type' => 'json',
        ])->save();
    }

    private function ownerFile(Request $request, string $code): ?SharedFile
    {
        return $this->ownerFileByToken($code, (string) $request->input('ownerToken', ''));
    }

    private function ownerFileByToken(string $code, string $token): ?SharedFile
    {
        $normalized = strtoupper($code);
        if (! preg_match('/^[A-Z0-9]{4,12}$/', $normalized) || $token === '') {
            return null;
        }

        $file = SharedFile::query()->where('code', $normalized)->first();
        $setting = Setting::query()->where('group', 'file_owner')->where('key', $normalized)->latest('updated_at')->first();
        if (! $file || ! $setting || ! Hash::check($token, (string) $setting->value)) {
            return null;
        }

        return $file;
    }

    private function requiresRiskAcknowledgement(SharedFile $file): bool
    {
        if ($this->isHardBlockedRisk($file)) {
            return true;
        }

        if ($this->riskReviewStatus($file) === 'false_positive' && Setting::valueFor('risk', 'false_positive_policy', 'require_ack') === 'allow_direct') {
            return false;
        }

        return (bool) $file->malware_scan_checked_at && ! (bool) $file->malware_scan_passed;
    }

    private function isHardBlockedRisk(SharedFile $file): bool
    {
        if (! (bool) $file->malware_scan_checked_at) {
            return false;
        }

        if ($this->riskReviewStatus($file) === 'false_positive') {
            return false;
        }

        $details = is_array($file->malware_scan_details) ? $file->malware_scan_details : json_decode((string) $file->malware_scan_details, true);
        $threats = collect($this->extractThreatTypes(is_array($details) ? $details : []))
            ->map(fn ($threat): string => trim((string) $threat))
            ->filter()
            ->unique()
            ->values();

        $blockedThreats = [
            'adult_content',
            'sexual_content',
            'minor_sexual_content',
            'graphic_violence',
            'extreme_violence',
            'terrorism',
            'extremism',
            'drug_trade',
            'illegal_activity',
            'hate',
            'harassment',
        ];

        if ($threats->contains(fn (string $threat): bool => in_array($threat, $blockedThreats, true))) {
            return true;
        }

        if ((bool) $file->malware_scan_passed) {
            return false;
        }

        $mime = strtolower((string) $file->mime_type);

        return str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/');
    }

    private function extractThreatTypes(array $details): array
    {
        $threats = [];
        foreach (['threat_types', 'threats'] as $key) {
            foreach (($details[$key] ?? []) as $threat) {
                $threats[] = (string) $threat;
            }
        }

        foreach (($details['details'] ?? []) as $entry) {
            if (is_array($entry) && isset($entry['threat_type'])) {
                $threats[] = (string) $entry['threat_type'];
            }
        }

        foreach (($details['details']['archive_scan']['files'] ?? []) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            foreach (($entry['threats'] ?? []) as $threat) {
                $threats[] = (string) $threat;
            }
        }

        return array_values(array_unique(array_filter($threats)));
    }

    private function riskReviewStatus(SharedFile $file): ?string
    {
        $value = Setting::valueFor('risk_review', (string) $file->id, null);
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        return is_array($decoded) ? (string) ($decoded['status'] ?? '') : null;
    }

    private function hasValidRiskAcknowledgement(Request $request, SharedFile $file): bool
    {
        if ((string) $request->query('risk_ack') !== '1') {
            return false;
        }

        $expires = (int) $request->query('risk_expires');
        if ($expires < time()) {
            return false;
        }

        $signature = (string) $request->query('risk_signature');
        if ($signature === '') {
            return false;
        }

        return hash_equals($this->riskAcknowledgementSignature($file, $expires), $signature);
    }

    private function riskAcknowledgementSignature(SharedFile $file, int $expires): string
    {
        $checkedAt = $file->malware_scan_checked_at ? strtotime((string) $file->malware_scan_checked_at) : 0;
        $payload = implode('|', [$file->code, $file->id, $checkedAt, $expires]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    private function recordFailedUpload(Request $request, string $message, int $status, ?UploadedFile $uploaded = null): JsonResponse
    {
        FileUpload::query()->create([
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'original_name' => $uploaded ? $this->sanitizeFileName($uploaded->getClientOriginalName()) : null,
            'size' => $uploaded ? $uploaded->getSize() : 0,
            'mime_type' => $uploaded ? $uploaded->getClientMimeType() : null,
            'success' => false,
            'failure_reason' => $message,
            'created_at' => now(),
        ]);

        return ApiEnvelope::error($message, $status, $status);
    }

    private function recordDownload(?SharedFile $file, Request $request, string $code, bool $success, ?string $failureReason = null, int $bytes = 0): void
    {
        FileDownload::query()->create([
            'file_id' => $file ? $file->id : null,
            'code' => $code,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'referer' => substr((string) $request->headers->get('referer'), 0, 2000),
            'success' => $success,
            'failure_reason' => $failureReason,
            'bytes' => $bytes,
            'created_at' => now(),
        ]);
    }

    private function downloadRateLimited(Request $request, SharedFile $file): bool
    {
        $ip = $request->ip();
        $recentIpDownloads = FileDownload::query()
            ->where('ip', $ip)
            ->where('success', true)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();
        if ($recentIpDownloads >= (int) Setting::valueFor('risk', 'download_ip_10m_limit', 80)) {
            $this->recordDownloadRisk($request, $file, 'ip_10m_limit', $recentIpDownloads);
            return true;
        }

        $recentFileDownloads = FileDownload::query()
            ->where('ip', $ip)
            ->where('file_id', $file->id)
            ->where('success', true)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentFileDownloads >= (int) Setting::valueFor('risk', 'download_file_10m_limit', 20)) {
            $this->recordDownloadRisk($request, $file, 'file_10m_limit', $recentFileDownloads);
            return true;
        }

        return false;
    }

    private function recordDownloadRisk(Request $request, SharedFile $file, string $reason, int $count): void
    {
        try {
            Setting::query()->create([
                'group' => 'download_risk',
                'key' => now()->format('YmdHis').':'.substr(hash('sha256', $request->ip().'|'.$file->id.'|'.$reason), 0, 10),
                'value' => json_encode([
                    'ip' => $request->ip(),
                    'file_id' => $file->id,
                    'code' => $file->code,
                    'reason' => $reason,
                    'count' => $count,
                    'status' => 'observing',
                    'observed_until' => now()->addDay()->toDateTimeString(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                    'created_at' => now()->toDateTimeString(),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'type' => 'json',
            ]);
        } catch (Throwable) {
            report(new \RuntimeException('Download risk write failed'));
        }
    }

    private function recordRiskAcknowledgement(SharedFile $file, Request $request, string $code): void
    {
        try {
            if (! Schema::hasTable('risk_download_ack_logs')) {
                return;
            }

            $expires = (int) $request->query('risk_expires');
            RiskDownloadAckLog::query()->create([
                'file_id' => $file->id,
                'code' => $code,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                'referer' => substr((string) $request->headers->get('referer'), 0, 2000),
                'risk_score' => (int) ($file->risk_score ?? 0),
                'threat_summary' => $this->threatSummary($file),
                'scan_checked_at' => $file->malware_scan_checked_at,
                'signature_expires_at' => $expires > 0 ? date('Y-m-d H:i:s', $expires) : null,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            report(new \RuntimeException('Risk acknowledgement audit write failed'));
        }
    }

    private function threatSummary(SharedFile $file): string
    {
        $details = is_array($file->malware_scan_details) ? $file->malware_scan_details : json_decode((string) $file->malware_scan_details, true);
        $threats = collect($details['threat_types'] ?? $details['threats'] ?? [])
            ->map(fn ($threat): string => trim((string) $threat))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        if ($threats !== []) {
            return implode('; ', $threats);
        }

        return 'AI 恶意代码扫描检测到威胁';
    }

    private function previewMimeType(SharedFile $file): ?string
    {
        $mime = strtolower((string) ($file->mime_type ?: 'application/octet-stream'));
        $extension = strtolower((string) ($file->extension ?: pathinfo($file->original_name, PATHINFO_EXTENSION)));

        if ($mime === 'application/pdf' || $extension === 'pdf') {
            return 'application/pdf';
        }

        if (str_starts_with($mime, 'image/') && ! in_array($mime, ['image/svg+xml'], true)) {
            return $mime;
        }

        if (str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/')) {
            return $mime;
        }

        $textExtensions = ['txt', 'md', 'csv', 'json', 'log', 'xml', 'yaml', 'yml'];
        if (str_starts_with($mime, 'text/') || in_array($extension, $textExtensions, true)) {
            return 'text/plain; charset=utf-8';
        }

        return null;
    }

    private function safeHeaderFilename(string $name): string
    {
        $name = str_replace(['\\', '/', '"', "\r", "\n"], '_', $name ?: 'preview');

        return preg_replace('/[^\x20-\x7E]/', '_', $name) ?: 'preview';
    }

    private function firstInput(Request $request, array $names)
    {
        foreach ($names as $name) {
            $value = $request->input($name);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function expireDays(Request $request, int $default, int $max): int
    {
        $candidate = (int) ($this->firstInput($request, ['expireDays', 'expire_days', 'expiresInDays', 'expire']) ?: $default);

        return max(1, min($candidate, $max));
    }

    private function allowedTypes(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $type): string => strtolower(trim($type)))
            ->filter()
            ->values()
            ->all();
    }

    private function captchaLooksPresent(Request $request): bool
    {
        foreach ([
            'lot_number',
            'captcha_output',
            'pass_token',
            'gen_time',
            'lotNumber',
            'captchaOutput',
            'passToken',
            'genTime',
            'captchaToken',
            'captcha_token',
        ] as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }

    private function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (SharedFile::query()->where('code', $code)->exists());

        return $code;
    }

    private function sanitizeFileName(string $name): string
    {
        $name = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $name));
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'file');

        return mb_substr($name ?: 'file', 0, 180);
    }
}
