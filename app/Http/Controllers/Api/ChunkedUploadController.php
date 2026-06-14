<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ComputeFileHash;
use App\Jobs\ScanUploadedFile;
use App\Models\ChunkedUpload;
use App\Models\FileUpload;
use App\Models\SharedFile;
use App\Support\ApiEnvelope;
use App\Support\YeyuFileExpressSettings;
use App\Support\RiskEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChunkedUploadController extends Controller
{
    private RiskEvaluator $riskEvaluator;

    public function __construct(RiskEvaluator $riskEvaluator)
    {
        $this->riskEvaluator = $riskEvaluator;
    }

    public function init(Request $request): JsonResponse
    {
        if (! (bool) config('yeyu_file_express.chunked_upload.enabled', true)) {
            return ApiEnvelope::error('分片上传暂未开启', 403, 403);
        }

        try {
            $data = $request->validate([
                'originalName' => ['required', 'string', 'max:180'],
                'mimeType' => ['nullable', 'string', 'max:255'],
                'totalSize' => ['required', 'integer', 'min:1'],
                'chunkSize' => ['required', 'integer', 'min:1'],
                'totalChunks' => ['required', 'integer', 'min:1'],
                'expireDays' => ['nullable', 'integer', 'min:1'],
                'extractCode' => ['nullable', 'string', 'min:4', 'max:6'],
                'shareTheme' => ['nullable', 'string', 'max:64'],
                'sha256' => ['nullable', 'string', 'size:64', 'regex:/^[a-fA-F0-9]{64}$/'],
            ]);
        } catch (ValidationException $exception) {
            return ApiEnvelope::error('参数不完整或格式无效', 422, 422, $exception->errors());
        }

        $uploadConfig = YeyuFileExpressSettings::upload();
        $chunkConfig = YeyuFileExpressSettings::chunkedUpload();
        $maxSize = (int) $uploadConfig['maxFileSize'];
        if ((int) $data['totalSize'] > $maxSize) {
            return ApiEnvelope::error('文件超过最大上传限制', 413, 413);
        }

        if ((int) $data['chunkSize'] > (int) ($chunkConfig['maxChunkSize'] ?? 10_485_760)) {
            return ApiEnvelope::error('分片超过最大限制', 413, 413);
        }

        if ((int) $data['totalChunks'] > (int) ($chunkConfig['maxChunks'] ?? 10_000)) {
            return ApiEnvelope::error('分片数量超过限制', 422, 422);
        }

        $originalName = $this->sanitizeFileName((string) $data['originalName']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedTypes = $this->allowedTypes((string) $uploadConfig['allowedFileTypes']);
        if ($allowedTypes !== [] && $extension !== '' && ! in_array($extension, $allowedTypes, true)) {
            return ApiEnvelope::error('文件类型不允许上传', 422, 422);
        }

        $risk = $this->riskEvaluator->evaluateUpload($request, $originalName, $extension, $data['mimeType'] ?? null, (int) $data['totalSize']);
        if ($risk['blocked']) {
            return ApiEnvelope::error('上传触发风控拦截', 403, 403);
        }

        $expireDays = $this->expireDays($request, (int) $uploadConfig['defaultExpireDays'], (int) $uploadConfig['maxExpireDays']);
        $extractCode = strtoupper(trim((string) ($data['extractCode'] ?? '')));
        $uploadId = 'chunk_'.Str::random(32);
        $directory = 'chunks/'.now()->format('Y/m/d').'/'.$uploadId;
        $disk = config('filesystems.default', 'local');

        $upload = ChunkedUpload::query()->create([
            'upload_id' => $uploadId,
            'original_name' => $originalName,
            'mime_type' => $data['mimeType'] ?? 'application/octet-stream',
            'extension' => $extension ?: null,
            'disk' => $disk,
            'directory' => $directory,
            'total_size' => (int) $data['totalSize'],
            'chunk_size' => (int) $data['chunkSize'],
            'total_chunks' => (int) $data['totalChunks'],
            'received_chunks' => 0,
            'received_bytes' => 0,
            'sha256' => isset($data['sha256']) ? strtolower((string) $data['sha256']) : null,
            'extract_code_hash' => $extractCode !== '' ? Hash::make($extractCode) : null,
            'has_extract_code' => $extractCode !== '',
            'share_theme' => (string) ($data['shareTheme'] ?? 'default'),
            'expire_days' => $expireDays,
            'status' => 'pending',
            'risk_score' => $risk['score'],
            'risk_reasons_json' => $risk['reasons'],
            'uploader_ip' => $request->ip(),
            'uploader_user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'expires_at' => now()->addMinutes((int) ($chunkConfig['sessionTtlMinutes'] ?? 120)),
        ]);

        return ApiEnvelope::ok($this->uploadPayload($upload), '分片上传已初始化');
    }

    public function chunk(Request $request, string $uploadId): JsonResponse
    {
        $upload = $this->findPendingUpload($uploadId);
        if (! $upload) {
            return ApiEnvelope::error('分片上传会话不存在或已失效', 404, 404);
        }

        if (! $this->requestMatchesUploadOwner($request, $upload)) {
            return ApiEnvelope::error('分片上传令牌无效', 403, 403);
        }

        $uploaded = $request->file('chunk');
        if (! $uploaded instanceof UploadedFile || ! $uploaded->isValid()) {
            return ApiEnvelope::error('请选择要上传的分片', 422, 422);
        }

        $index = (int) $request->input('index', -1);
        if ($index < 0 || $index >= $upload->total_chunks) {
            return ApiEnvelope::error('分片序号无效', 422, 422);
        }

        if ($uploaded->getSize() > max(1, (int) YeyuFileExpressSettings::chunkedUpload()['maxChunkSize'])) {
            return ApiEnvelope::error('分片超过最大限制', 413, 413);
        }

        $path = $this->chunkPath($upload, $index);
        $disk = Storage::disk($upload->disk);
        $previousSize = $disk->exists($path) ? (int) $disk->size($path) : null;
        $stream = fopen($uploaded->getRealPath(), 'rb');
        $disk->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $this->recordReceivedChunk($upload, (int) $uploaded->getSize(), $previousSize);
        $upload->refresh();

        return ApiEnvelope::ok($this->uploadPayload($upload) + [
            'index' => $index,
            'complete' => $upload->received_chunks >= $upload->total_chunks,
        ], '分片已保存');
    }

    public function complete(Request $request, string $uploadId): JsonResponse
    {
        $upload = $this->findPendingUpload($uploadId);
        if (! $upload) {
            return ApiEnvelope::error('分片上传会话不存在或已失效', 404, 404);
        }

        if (! $this->requestMatchesUploadOwner($request, $upload)) {
            return ApiEnvelope::error('分片上传令牌无效', 403, 403);
        }

        $this->refreshReceivedStats($upload);
        $upload->refresh();

        $missing = $this->missingChunks($upload);
        if ($missing !== []) {
            return ApiEnvelope::error('仍有分片未上传', 422, 422, ['missingChunks' => $missing]);
        }

        $disk = $upload->disk;
        $code = $this->generateCode();
        $storedName = (string) Str::uuid().($upload->extension ? ".{$upload->extension}" : '');
        $path = 'uploads/'.now()->format('Y/m/d').'/'.$storedName;
        $tmpPath = storage_path('app/tmp/chunked/'.$upload->upload_id.'.upload');

        if (! is_dir(dirname($tmpPath))) {
            mkdir(dirname($tmpPath), 0755, true);
        }

        $out = fopen($tmpPath, 'wb');
        for ($index = 0; $index < $upload->total_chunks; $index++) {
            $in = Storage::disk($disk)->readStream($this->chunkPath($upload, $index));
            if (is_resource($in) && is_resource($out)) {
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        }
        if (is_resource($out)) {
            fclose($out);
        }

        if (filesize($tmpPath) !== $upload->total_size) {
            @unlink($tmpPath);

            return ApiEnvelope::error('合并后的文件大小不一致', 422, 422);
        }

        $sha256 = hash_file('sha256', $tmpPath) ?: null;
        if ($upload->sha256 && $sha256 && ! hash_equals(strtolower((string) $upload->sha256), strtolower($sha256))) {
            @unlink($tmpPath);
            $upload->update(['status' => 'failed', 'sha256' => $sha256]);

            return ApiEnvelope::error('文件 SHA256 校验失败，请重新上传', 422, 422);
        }
        $stream = fopen($tmpPath, 'rb');
        Storage::disk($disk)->put($path, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }
        @unlink($tmpPath);
        if (! Storage::disk($disk)->exists($path)) {
            return ApiEnvelope::error('文件写入失败', 500, 500);
        }

        $file = SharedFile::query()->create([
            'code' => $code,
            'original_name' => $upload->original_name,
            'stored_name' => $storedName,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $upload->mime_type ?: 'application/octet-stream',
            'extension' => $upload->extension,
            'size' => $upload->total_size,
            'sha256' => null,
            'scan_status' => 'pending',
            'risk_score' => $upload->risk_score,
            'risk_reasons_json' => $upload->risk_reasons_json ?: [],
            'extract_code_hash' => $upload->extract_code_hash,
            'has_extract_code' => $upload->has_extract_code,
            'share_theme' => $upload->share_theme,
            'status' => 'active',
            'expires_at' => now()->addDays($upload->expire_days),
            'uploaded_at' => now(),
            'uploader_ip' => $upload->uploader_ip,
            'uploader_user_agent' => $upload->uploader_user_agent,
        ]);

        $upload->update([
            'status' => 'completed',
            'completed_file_id' => $file->id,
            'sha256' => $sha256,
        ]);

        Storage::disk($disk)->deleteDirectory($upload->directory);
        ComputeFileHash::dispatch($file->id);
        ScanUploadedFile::dispatch($file->id);

        FileUpload::query()->create([
            'file_id' => $file->id,
            'ip' => $upload->uploader_ip,
            'user_agent' => $upload->uploader_user_agent,
            'original_name' => $upload->original_name,
            'size' => $file->size,
            'mime_type' => $file->mime_type,
            'success' => true,
            'created_at' => now(),
        ]);

        return ApiEnvelope::ok($this->publicFile($file), '上传成功');
    }

    public function cancel(Request $request, string $uploadId): JsonResponse
    {
        $upload = ChunkedUpload::query()
            ->where('upload_id', $uploadId)
            ->whereIn('status', ['pending', 'expired'])
            ->first();

        if (! $upload) {
            return ApiEnvelope::error('分片上传会话不存在或已结束', 404, 404);
        }

        if (! $this->requestMatchesUploadOwner($request, $upload)) {
            return ApiEnvelope::error('分片上传令牌无效', 403, 403);
        }

        Storage::disk($upload->disk)->deleteDirectory($upload->directory);
        $upload->update(['status' => 'cancelled']);

        return ApiEnvelope::ok($this->uploadPayload($upload->refresh()), '分片上传已取消');
    }

    private function findPendingUpload(string $uploadId): ?ChunkedUpload
    {
        $upload = ChunkedUpload::query()->where('upload_id', $uploadId)->where('status', 'pending')->first();
        if (! $upload) {
            return null;
        }

        if ($upload->isExpired()) {
            $upload->update(['status' => 'expired']);

            return null;
        }

        return $upload;
    }

    private function recordReceivedChunk(ChunkedUpload $upload, int $currentSize, ?int $previousSize): void
    {
        $chunkDelta = $previousSize === null ? 1 : 0;
        $byteDelta = $currentSize - (int) ($previousSize ?? 0);
        $byteExpression = $byteDelta >= 0
            ? 'received_bytes + '.$byteDelta
            : 'CASE WHEN received_bytes >= '.abs($byteDelta).' THEN received_bytes - '.abs($byteDelta).' ELSE 0 END';

        ChunkedUpload::query()
            ->whereKey($upload->id)
            ->update([
                'received_chunks' => DB::raw('received_chunks + '.$chunkDelta),
                'received_bytes' => DB::raw($byteExpression),
                'updated_at' => now(),
            ]);
    }

    private function refreshReceivedStats(ChunkedUpload $upload): void
    {
        $bytes = 0;
        $count = 0;
        for ($index = 0; $index < $upload->total_chunks; $index++) {
            $path = $this->chunkPath($upload, $index);
            if (Storage::disk($upload->disk)->exists($path)) {
                $count++;
                $bytes += Storage::disk($upload->disk)->size($path);
            }
        }

        $upload->update([
            'received_chunks' => $count,
            'received_bytes' => $bytes,
        ]);
    }

    private function requestMatchesUploadOwner(Request $request, ChunkedUpload $upload): bool
    {
        return hash_equals((string) $upload->uploader_ip, (string) $request->ip())
            && hash_equals((string) $upload->uploader_user_agent, substr((string) $request->userAgent(), 0, 2000));
    }

    /**
     * @return array<int,int>
     */
    private function missingChunks(ChunkedUpload $upload): array
    {
        $missing = [];
        for ($index = 0; $index < $upload->total_chunks; $index++) {
            if (! Storage::disk($upload->disk)->exists($this->chunkPath($upload, $index))) {
                $missing[] = $index;
            }
        }

        return $missing;
    }

    private function chunkPath(ChunkedUpload $upload, int $index): string
    {
        return $upload->directory.'/'.str_pad((string) $index, 6, '0', STR_PAD_LEFT).'.part';
    }

    private function uploadPayload(ChunkedUpload $upload): array
    {
        return [
            'uploadId' => $upload->upload_id,
            'originalName' => $upload->original_name,
            'totalSize' => $upload->total_size,
            'chunkSize' => $upload->chunk_size,
            'totalChunks' => $upload->total_chunks,
            'receivedChunks' => $upload->received_chunks,
            'receivedBytes' => $upload->received_bytes,
            'status' => $upload->status,
            'expiresAt' => $upload->expires_at ? $upload->expires_at->valueOf() : null,
        ];
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
            'downloadUrl' => route('api.files.download', ['code' => $file->code], false),
        ];
    }

    private function allowedTypes(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $type): string => strtolower(trim($type)))
            ->filter()
            ->values()
            ->all();
    }

    private function expireDays(Request $request, int $default, int $max): int
    {
        $candidate = (int) ($request->input('expireDays') ?: $request->input('expire_days') ?: $default);

        return max(1, min($candidate, $max));
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
