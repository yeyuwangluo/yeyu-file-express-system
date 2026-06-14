<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InstallationState
{
    public function isInstalled(): bool
    {
        $override = config('yeyu_file_express.installer.installed');

        if ($override !== null) {
            return (bool) $override;
        }

        if (app()->environment('testing')) {
            return true;
        }

        if ($this->markerExists()) {
            return true;
        }

        return $this->databaseLooksInstalled();
    }

    public function markerPath(): string
    {
        return (string) (config('yeyu_file_express.installer.marker_path') ?: storage_path('app/yeyu-file-express/installed.json'));
    }

    public function envPath(): string
    {
        return (string) (config('yeyu_file_express.installer.env_path') ?: base_path('.env'));
    }

    public function markerExists(): bool
    {
        return is_file($this->markerPath());
    }

    public function markInstalled(string $adminEmail): void
    {
        $path = $this->markerPath();
        $dir = dirname($path);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('无法创建安装标记目录：'.$dir);
        }

        $payload = [
            'installed_at' => now()->toIso8601String(),
            'admin_email' => $adminEmail,
            'php_version' => PHP_VERSION,
        ];

        if (file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            throw new \RuntimeException('无法写入安装标记文件：'.$path);
        }
    }

    public function checks(): array
    {
        return [
            'runtime' => [
                $this->check('PHP 版本', version_compare(PHP_VERSION, '8.0.2', '>='), PHP_VERSION.' / 要求 8.0.2+'),
                $this->extensionCheck('PDO', 'pdo'),
                $this->extensionCheck('OpenSSL', 'openssl'),
                $this->extensionCheck('Mbstring', 'mbstring'),
                $this->extensionCheck('Fileinfo', 'fileinfo'),
                $this->extensionCheck('Tokenizer', 'tokenizer'),
                $this->extensionCheck('XML', 'xml'),
                $this->extensionCheck('JSON', 'json'),
            ],
            'writable' => [
                $this->writableCheck('.env 配置文件', $this->envPath(), true),
                $this->writableCheck('storage 目录', storage_path()),
                $this->writableCheck('bootstrap/cache 目录', base_path('bootstrap/cache')),
                $this->writableCheck('database 目录', database_path()),
            ],
        ];
    }

    public function requiredChecksPass(): bool
    {
        foreach ($this->checks() as $group) {
            foreach ($group as $check) {
                if (($check['required'] ?? true) && ! $check['ok']) {
                    return false;
                }
            }
        }

        return true;
    }

    private function databaseLooksInstalled(): bool
    {
        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('settings')) {
                return false;
            }

            return User::query()
                ->where('is_admin', true)
                ->where('status', 'active')
                ->exists();
        } catch (Throwable $exception) {
            return false;
        }
    }

    private function extensionCheck(string $label, string $extension): array
    {
        return $this->check($label, extension_loaded($extension), extension_loaded($extension) ? '已启用' : '未启用');
    }

    private function writableCheck(string $label, string $path, bool $fileMayNotExist = false): array
    {
        if ($fileMayNotExist && ! is_file($path)) {
            $target = dirname($path);
            $ok = is_dir($target) && is_writable($target);

            return $this->check($label, $ok, $ok ? '可创建' : '不可创建：'.$path);
        }

        return $this->check($label, is_writable($path), is_writable($path) ? '可写' : '不可写：'.$path);
    }

    private function check(string $label, bool $ok, string $detail, bool $required = true): array
    {
        return [
            'label' => $label,
            'ok' => $ok,
            'detail' => $detail,
            'required' => $required,
        ];
    }
}
