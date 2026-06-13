<?php

namespace App\Http\Middleware;

use App\Support\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installation = app(InstallationState::class);
        $isInstalled = $installation->isInstalled();

        if ($isInstalled || $request->is('install') || $request->is('install/*')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*') || $request->is('api/v1/*')) {
            return response()->json([
                'code' => 503,
                'message' => '系统尚未安装',
                'data' => [
                    'installUrl' => url('/install'),
                ],
                'timestamp' => now()->valueOf(),
            ], 503);
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return response('系统尚未安装', 503);
        }

        return redirect('/install');
    }
}
