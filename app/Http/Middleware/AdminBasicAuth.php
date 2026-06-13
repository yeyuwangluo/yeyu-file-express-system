<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');

        if (app()->environment('testing') && $request->headers->get('X-Test-Admin') === '1') {
            $request->attributes->set('admin_role', 'owner');

            return $next($request);
        }

        // 优先检查 session 登录状态（表单登录）
        if ($request->session()->get('admin_logged_in')) {
            $role = $request->session()->get('admin_role', 'owner');
            $userId = $request->session()->get('admin_user_id');
            $request->attributes->set('admin_role', $role);
            if ($userId) {
                try {
                    $user = User::query()->find($userId);
                    if ($user && $user->is_admin && $user->status === 'active') {
                        $request->attributes->set('admin_user', $user);
                    } else {
                        $request->session()->forget(['admin_logged_in', 'admin_email', 'admin_role', 'admin_user_id']);

                        return redirect()->route('admin-lite.login')->withErrors(['email' => '管理员账号已失效，请重新登录']);
                    }
                } catch (Throwable) {
                    $request->session()->forget(['admin_logged_in', 'admin_email', 'admin_role', 'admin_user_id']);

                    return redirect()->route('admin-lite.login')->withErrors(['email' => '管理员账号状态异常，请重新登录']);
                }
            }

            return $next($request);
        }

        // 未登录 session → 重定向到登录页面（而非弹出 Basic Auth 框）
        if (! $request->getUser()) {
            return redirect()->route('admin-lite.login');
        }

        if (RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return response('Too Many Attempts', 429);
        }

        $admin = $this->resolveAdmin($request);
        if (! $admin && ($request->getUser() !== $email || ! $this->passwordIsValid((string) $request->getPassword()))) {
            RateLimiter::hit($this->throttleKey($request), 300);

            return redirect()->route('admin-lite.login')->withErrors(['email' => '邮箱或密码不正确']);
        }

        RateLimiter::clear($this->throttleKey($request));
        if ($admin instanceof User) {
            $request->attributes->set('admin_user', $admin);
            $request->attributes->set('admin_role', $admin->role ?: 'admin');
            $this->recordLogin($request, $admin->email);
        } else {
            $request->attributes->set('admin_role', 'owner');
            $this->recordLogin($request, $email);
        }

        return $next($request);
    }

    private function resolveAdmin(Request $request): ?User
    {
        $email = (string) $request->getUser();
        $password = (string) $request->getPassword();
        if ($email === '' || $password === '') {
            return null;
        }

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

    private function passwordIsValid(string $password): bool
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

    private function throttleKey(Request $request): string
    {
        return 'admin-login:'.$request->ip();
    }

    private function recordLogin(Request $request, string $email): void
    {
        try {
            User::query()
                ->where('email', $email)
                ->where('is_admin', true)
                ->update(['last_login_at' => now(), 'last_login_ip' => $request->ip()]);
        } catch (Throwable) {
            //
        }
    }
}
