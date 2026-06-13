<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    public static function write(Request $request, string $action, ?string $targetType = null, ?int $targetId = null, array $metadata = []): void
    {
        AuditLog::query()->create([
            'user_id' => null,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'metadata_json' => $metadata,
            'created_at' => now(),
        ]);
    }
}
