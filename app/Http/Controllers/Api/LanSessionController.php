<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LanSession;
use App\Models\LanSignal;
use App\Support\ApiEnvelope;
use App\Support\XiaoxinFileExpressSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LanSessionController extends Controller
{
    public function index(): JsonResponse
    {
        $config = XiaoxinFileExpressSettings::lanTransfer();
        if (! $config["enabled"]) {
            return ApiEnvelope::error("局域网互传暂未开启", 403, 403);
        }

        return ApiEnvelope::ok([
            "enabled" => true,
            "maxFileSize" => $config["maxFileSize"],
            "maxFileCount" => $config["maxFileCount"],
            "maxTotalSize" => $config["maxTotalSize"],
            "maxTotalSizeEnabled" => $config["maxTotalSizeEnabled"],
            "allowedFileTypes" => $config["allowedFileTypes"],
            "expireMinutes" => $config["expireMinutes"],
            "completedRetentionMinutes" => $config["completedRetentionMinutes"],
            "textEnabled" => $config["textEnabled"],
            "textMaxLength" => $config["textMaxLength"],
            "textMaxLines" => $config["textMaxLines"],
            "textAllowTitle" => $config["textAllowTitle"],
            "textRetentionMinutes" => $config["textRetentionMinutes"],
            "serverTime" => ApiEnvelope::timestamp(),
        ], "局域网互传配置获取成功");
    }
    
    public function store(Request $request): JsonResponse
    {
        $config = XiaoxinFileExpressSettings::lanTransfer();
        if (! $config['enabled']) {
            return ApiEnvelope::error('局域网互传暂未开启', 403, 403);
        }

        $isText = $request->input('transferKind') === 'text';
        if ($isText && ! $config['textEnabled']) {
            return ApiEnvelope::error('文本互传暂未开启', 403, 403);
        }

        $content = (string) $request->input('textContent', '');
        if ($isText) {
            if (mb_strlen($content) > (int) $config['textMaxLength']) {
                return ApiEnvelope::error('文本内容超过长度限制', 422, 422);
            }
            if (($content === '' ? 0 : count(preg_split('/\R/u', $content))) > (int) $config['textMaxLines']) {
                return ApiEnvelope::error('文本内容超过行数限制', 422, 422);
            }
        }

        $inputFiles = collect($request->input('files', []))->values();
        if (! $isText && $inputFiles->count() > (int) $config['maxFileCount']) {
            return ApiEnvelope::error('文件数量超过限制', 422, 422);
        }

        $files = $isText
            ? [[
                'id' => 'text-payload',
                'fileName' => (string) ($request->input('textTitle') ?: '文本内容'),
                'fileSize' => strlen($content),
                'mimeType' => 'text/plain',
            ]]
            : $inputFiles->map(fn ($file, int $index): array => [
                'id' => (string) ($file['id'] ?? 'file-'.($index + 1)),
                'fileName' => (string) ($file['fileName'] ?? '文件-'.($index + 1)),
                'fileSize' => (int) ($file['fileSize'] ?? 0),
                'mimeType' => (string) ($file['mimeType'] ?? 'application/octet-stream'),
            ])->all();

        if (! $isText && count($files) === 0) {
            return ApiEnvelope::error('请至少选择一个文件', 422, 422);
        }

        foreach ($files as $file) {
            if ((int) $file['fileSize'] > (int) $config['maxFileSize']) {
                return ApiEnvelope::error('单个文件大小超过限制', 422, 422);
            }
        }

        $allowedTypes = $this->allowedTypes((string) $config['allowedFileTypes']);
        if (! $isText && $allowedTypes !== []) {
            foreach ($files as $file) {
                $extension = strtolower(pathinfo((string) $file['fileName'], PATHINFO_EXTENSION));
                if ($extension !== '' && ! in_array($extension, $allowedTypes, true)) {
                    return ApiEnvelope::error('文件类型不允许互传', 422, 422);
                }
            }
        }

        $totalSize = array_sum(array_column($files, 'fileSize'));
        if (! $isText && $config['maxTotalSizeEnabled'] && $totalSize > (int) $config['maxTotalSize']) {
            return ApiEnvelope::error('文件总大小超过限制', 422, 422);
        }

        $senderToken = 'sender_'.Str::random(24);
        $receiverToken = 'receiver_'.Str::random(24);
        $session = LanSession::query()->create([
            'session_id' => 'lan_'.Str::random(16),
            'short_code' => $this->generateShortCode(),
            'transfer_kind' => $isText ? 'text' : 'file',
            'status' => 'waiting',
            'file_name' => $files[0]['fileName'] ?? '',
            'file_size' => $files[0]['fileSize'] ?? 0,
            'mime_type' => $files[0]['mimeType'] ?? '',
            'file_count' => count($files),
            'total_size' => $totalSize,
            'files_json' => $files,
            'text_title' => $isText ? (string) $request->input('textTitle', '') : '',
            'text_content' => $isText ? $content : '',
            'text_preview' => $isText ? mb_substr($content, 0, 160) : '',
            'text_length' => $isText ? mb_strlen($content) : 0,
            'text_line_count' => $isText && $content !== '' ? count(preg_split('/\R/u', $content)) : 0,
            'sender_token_hash' => Hash::make($senderToken),
            'receiver_token_hash' => Hash::make($receiverToken),
            'receiver_joined' => false,
            'expires_at' => now()->addMinutes((int) $config['expireMinutes']),
        ]);

        return ApiEnvelope::ok($this->sessionView($session) + ['senderToken' => $senderToken], '会话创建成功');
    }

    public function join(Request $request): JsonResponse
    {
        $session = $request->filled('sessionId')
            ? LanSession::query()->where('session_id', $request->input('sessionId'))->first()
            : LanSession::query()->where('short_code', strtoupper((string) $request->input('shortCode')))->latest()->first();

        if (! $session || $session->isExpired() || in_array($session->status, ['cancelled', 'expired'], true)) {
            return ApiEnvelope::error('当前互传会话已失效，请重新创建或重新加入', 404, 404);
        }

        $receiverToken = 'receiver_'.Str::random(24);
        $session->update([
            'receiver_joined' => true,
            'receiver_token_hash' => Hash::make($receiverToken),
        ]);

        return ApiEnvelope::ok($this->sessionView($session->refresh()) + ['receiverToken' => $receiverToken], '加入成功');
    }

    public function show(string $id): JsonResponse
    {
        $session = $this->findSession($id);
        if (! $session) {
            return ApiEnvelope::error('当前互传会话已失效，请重新创建或重新加入', 404, 404);
        }

        return ApiEnvelope::ok($this->sessionView($session), '获取会话成功');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $session = $this->findSession($id);
        if (! $session) {
            return ApiEnvelope::error('当前互传会话已失效，请重新创建或重新加入', 404, 404);
        }

        $role = $this->normalizeRole((string) ($request->input('role') ?: $request->query('role') ?: 'sender'));
        if (! $this->tokenIsValid($request, $session, $role)) {
            return ApiEnvelope::error('互传令牌无效', 403, 403);
        }

        $session->update(['status' => 'cancelled']);

        return ApiEnvelope::ok($this->sessionView($session->refresh()), '会话已取消');
    }

    public function text(Request $request, string $id): JsonResponse
    {
        $session = $this->findSession($id);
        if (! $session) {
            return ApiEnvelope::error('当前互传会话已失效，请重新创建或重新加入', 404, 404);
        }

        if (! $this->tokenIsValid($request, $session, 'receiver')) {
            return ApiEnvelope::error('互传令牌无效', 403, 403);
        }

        return ApiEnvelope::ok([
            'title' => $session->text_title,
            'content' => $session->text_content,
            'contentPreview' => $session->text_preview,
            'charLength' => $session->text_length,
            'lineCount' => $session->text_line_count,
        ], '获取文本成功');
    }

    public function completeText(Request $request, string $id): JsonResponse
    {
        return $this->complete($request, $id, '文本已确认接收');
    }

    public function updateStatus(Request $request, string $id): JsonResponse
    {
        $session = $this->findSession($id);
        if (! $session) {
            return ApiEnvelope::error('当前互传会话已失效，请重新创建或重新加入', 404, 404);
        }

        $role = $this->normalizeRole((string) ($request->input('role') ?: $request->query('role') ?: 'sender'));
        if (! $this->tokenIsValid($request, $session, $role)) {
            return ApiEnvelope::error('互传令牌无效', 403, 403);
        }

        $session->update(['status' => (string) ($request->input('status') ?: $session->status)]);

        return ApiEnvelope::ok($this->sessionView($session->refresh()), '状态已更新');
    }

    public function complete(Request $request, string $id, string $message = '会话已完成'): JsonResponse
    {
        $session = $this->findSession($id);
        if (! $session) {
            return ApiEnvelope::error('当前互传会话已失效，请重新创建或重新加入', 404, 404);
        }

        $role = $this->normalizeRole((string) ($request->input('role') ?: $request->query('role') ?: 'receiver'));
        if (! $this->tokenIsValid($request, $session, $role)) {
            return ApiEnvelope::error('互传令牌无效', 403, 403);
        }

        $session->update(['status' => 'completed', 'delivered_at' => now()]);

        return ApiEnvelope::ok($this->sessionView($session->refresh()), $message);
    }

    public function storeSignal(Request $request, string $id, string $type): JsonResponse
    {
        $session = $this->findSession($id);
        if (! $session || ! in_array($type, ['offer', 'answer', 'ice'], true)) {
            return ApiEnvelope::error('当前互传会话已失效，请重新创建或重新加入', 404, 404);
        }

        $role = $this->normalizeRole((string) ($request->input('role') ?: $request->query('role') ?: 'sender'));
        if (! $this->tokenIsValid($request, $session, $role)) {
            return ApiEnvelope::error('互传令牌无效', 403, 403);
        }

        if ($type === 'ice' && is_array($request->input('candidates'))) {
            $signals = [];
            $sequence = ((int) LanSignal::query()->where('lan_session_id', $session->id)->where('type', $type)->max('sequence')) + 1;
            foreach ($request->input('candidates') as $candidate) {
                $signal = LanSignal::query()->create([
                    'lan_session_id' => $session->id,
                    'type' => $type,
                    'role' => $role,
                    'sequence' => $sequence++,
                    'payload_json' => is_array($candidate) ? $candidate : ['candidate' => (string) $candidate],
                    'created_at' => now(),
                ]);
                $signals[] = $this->signalPayload($signal, $type);
            }
            $session->touch();

            return ApiEnvelope::ok($signals, '候选已批量保存');
        }

        $sequence = ((int) LanSignal::query()->where('lan_session_id', $session->id)->where('type', $type)->max('sequence')) + 1;
        $payload = $request->except(['token']);
        
        $signal = LanSignal::query()->create([
            'lan_session_id' => $session->id,
            'type' => $type,
            'role' => $role,
            'sequence' => $sequence,
            'payload_json' => $payload,
            'created_at' => now(),
        ]);
        $session->touch();

        return ApiEnvelope::ok($this->signalPayload($signal, $type), $type === 'ice' ? '候选已保存' : '保存成功');
    }

    public function getSignal(Request $request, string $id, string $type): JsonResponse
    {
        $session = $this->findSession($id);
        if (! $session || ! in_array($type, ['offer', 'answer', 'ice'], true)) {
            return ApiEnvelope::error('当前互传会话已失效，请重新创建或重新加入', 404, 404);
        }

        $role = $this->normalizeRole((string) ($request->query('role') ?: 'receiver'));
        if (! $this->tokenIsValid($request, $session, $role)) {
            return ApiEnvelope::error('互传令牌无效', 403, 403);
        }

        if ($type !== 'ice') {
            $signal = LanSignal::query()
                ->where('lan_session_id', $session->id)
                ->where('type', $type)
                ->latest('id')
                ->first();

            $payload = $signal ? $signal->payload_json : null;
            
            // 确保不返回null，返回空对象
            if ($payload === null) {
                $payload = new \stdClass();
            }

            return ApiEnvelope::ok($payload, '获取成功');
        }

        $after = (int) ($request->query('after') ?: $request->query('afterSequence') ?: 0);
        $role = $request->query('role');
        $signals = LanSignal::query()
            ->where('lan_session_id', $session->id)
            ->where('type', 'ice')
            ->when($role, fn ($query) => $query->where('role', $role))
            ->where('sequence', '>', $after)
            ->orderBy('sequence')
            ->get();

        $items = $signals
            ->map(fn (LanSignal $signal): array => $this->signalPayload($signal, 'ice'))
            ->values();

        $payload = [
            'items' => $items,
            'nextAfterSequence' => (int) ($signals->max('sequence') ?? $after),
            'serverTime' => ApiEnvelope::timestamp(),
        ];

        foreach ($items as $index => $item) {
            $payload[$index] = $item;
        }

        return ApiEnvelope::ok($payload, '获取成功');
    }

    private function findSession(string $id): ?LanSession
    {
        $session = LanSession::query()->where('session_id', $id)->first();

        if (! $session || $session->isExpired()) {
            if ($session && $session->status !== 'expired') {
                $session->update(['status' => 'expired']);
            }

            return null;
        }

        return $session;
    }

    private function sessionView(LanSession $session): array
    {
        return [
            'id' => $session->session_id,
            'sessionId' => $session->session_id,
            'shortCode' => $session->short_code,
            'transferKind' => $session->transfer_kind,
            'status' => $session->status,
            'fileName' => $session->file_name,
            'fileSize' => $session->file_size,
            'mimeType' => $session->mime_type,
            'fileCount' => $session->file_count,
            'totalSize' => $session->total_size,
            'files' => $session->files_json ?: [],
            'textTitle' => $session->text_title,
            'textPreview' => $session->text_preview,
            'textLength' => $session->text_length,
            'textLineCount' => $session->text_line_count,
            'deliveredAt' => $session->delivered_at ? $session->delivered_at->valueOf() : null,
            'expiresAt' => $session->expires_at ? $session->expires_at->valueOf() : null,
            'createdAt' => $session->created_at ? $session->created_at->valueOf() : null,
            'updatedAt' => $session->updated_at ? $session->updated_at->valueOf() : null,
            'receiverJoined' => $session->receiver_joined,
        ];
    }


    public function cleanSDP(string $sdp): string
    {
        // Convert modern datachannel SDP to the legacy syntax required by older parsers.
        $normalized = str_replace(["\r\n", "\r"], "\n", $sdp);
        $lines = explode("\n", $normalized);
        $cleaned = [];
        $sctpPort = '5000';
        $pendingSctpMap = false;

        foreach ($lines as $line) {
            if (preg_match('/^a=sctp-port:(\d+)/', trim($line), $matches)) {
                $sctpPort = $matches[1];
                break;
            }
        }
        
        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (preg_match('/^m=application\s+(\S+)\s+UDP\/DTLS\/SCTP\s+webrtc-datachannel$/', $trimmed, $matches)) {
                $cleaned[] = "m=application {$matches[1]} DTLS/SCTP {$sctpPort}";
                $pendingSctpMap = true;
                continue;
            }

            if ($pendingSctpMap && str_starts_with($trimmed, 'a=mid:')) {
                $cleaned[] = $line;
                $cleaned[] = "a=sctpmap:{$sctpPort} webrtc-datachannel 1024";
                $pendingSctpMap = false;
                continue;
            }

            if (preg_match('/^a=(max-message-size|sctp-port|sctp-max-message-size):/', $trimmed)) {
                continue;
            }

            $cleaned[] = $line;
        }

        if ($pendingSctpMap) {
            $cleaned[] = "a=sctpmap:{$sctpPort} webrtc-datachannel 1024";
        }
        
        return implode("\r\n", $cleaned);
    }
    private function signalPayload(LanSignal $signal, string $type): array
    {
        $payload = $signal->payload_json ?: [];

        if ($type !== 'ice') {
            return $payload;
        }

        return [
            'id' => $signal->id,
            'sequence' => $signal->sequence,
            'role' => $signal->role,
            'payload' => $payload['payload'] ?? $payload,
            'candidate' => $payload['candidate'] ?? null,
            'createdAt' => $signal->created_at ? $signal->created_at->valueOf() : null,
        ];
    }

    private function generateShortCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
        } while (LanSession::query()->where('short_code', $code)->where('expires_at', '>', now())->exists());

        return $code;
    }

    private function tokenIsValid(Request $request, LanSession $session, string $role): bool
    {
        $token = (string) ($request->bearerToken() ?: $request->input('token') ?: $request->query('token'));
        if ($token === '') {
            return false;
        }

        $hash = $role === 'receiver' ? $session->receiver_token_hash : $session->sender_token_hash;

        return is_string($hash) && $hash !== '' && Hash::check($token, $hash);
    }

    private function normalizeRole(string $role): string
    {
        return in_array($role, ['sender', 'receiver'], true) ? $role : 'sender';
    }

    private function allowedTypes(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $type): string => strtolower(trim($type)))
            ->filter()
            ->values()
            ->all();
    }
}
