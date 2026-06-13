<?php

namespace App\Http\Middleware;

use App\Models\BlockedIp;
use Closure;
use Illuminate\Http\Request;

class CheckBlockedIp
{
    public function handle(Request $request, Closure $next, string $scope = 'all')
    {
        $ip = $request->ip();
        
        if (BlockedIp::matches($ip, 'all') || BlockedIp::matches($ip, $scope)) {
            $blockedIp = BlockedIp::query()
                ->where('ip', $ip)
                ->where(function($query) use ($scope) {
                    $query->where('scope', 'all')
                          ->orWhere('scope', $scope);
                })
                ->where(function($query) {
                    $query->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                })
                ->latest('created_at')
                ->first();
            
            $isApi = $request->expectsJson();
            
            if ($isApi) {
                return response()->json([
                    'code' => 403,
                    'message' => '您的IP地址已被暂时封禁，请24小时后再试',
                    'data' => null,
                ], 403);
            } else {
                return response()->view('blocked', [
                    'ip' => $ip,
                    'reason' => $blockedIp->reason ?? '上传了包含病毒的文件',
                    'expires_at' => $blockedIp->expires_at,
                ], 403);
            }
        }
        
        return $next($request);
    }
}
