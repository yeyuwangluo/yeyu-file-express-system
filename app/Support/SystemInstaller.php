<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SystemInstaller
{
    public function __construct(private InstallationState $state)
    {
    }

    public function install(array $data): void
    {
        if (! $this->state->requiredChecksPass()) {
            throw new RuntimeException('环境检查未通过，请先修复标红项目。');
        }

        $database = $this->prepareDatabase($data);
        $this->writeEnvironment($data, $database);
        $this->applyRuntimeConfig($data, $database);
        $this->assertDatabaseConnection();

        if ($this->state->isInstalled()) {
            $this->runArtisan('migrate:fresh', ['--force' => true]);
        } else {
            $this->runArtisan('migrate', ['--force' => true]);
        }

        $this->runArtisan('db:seed', ['--force' => true]);
        $this->createAdmin($data);
        $this->tryStorageLink();
        $this->state->markInstalled((string) $data['admin_email']);
        $this->writeEnvironmentValues(['XIAOXIN_FILE_EXPRESS_INSTALLED' => 'true']);
        $this->runArtisan('config:clear');
    }

    private function prepareDatabase(array $data): array
    {
        if (($data['db_connection'] ?? 'sqlite') === 'mysql') {
            if (! extension_loaded('pdo_mysql')) {
                throw new RuntimeException('当前 PHP 未启用 pdo_mysql 扩展，不能使用 MySQL。');
            }

            return [
                'connection' => 'mysql',
                'env' => [
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => (string) $data['db_host'],
                    'DB_PORT' => (string) $data['db_port'],
                    'DB_DATABASE' => (string) $data['db_database'],
                    'DB_USERNAME' => (string) $data['db_username'],
                    'DB_PASSWORD' => (string) ($data['db_password'] ?? ''),
                ],
                'runtime' => [
                    'host' => (string) $data['db_host'],
                    'port' => (string) $data['db_port'],
                    'database' => (string) $data['db_database'],
                    'username' => (string) $data['db_username'],
                    'password' => (string) ($data['db_password'] ?? ''),
                ],
            ];
        }

        $envPath = trim((string) ($data['sqlite_path'] ?? '')) ?: 'database/database.sqlite';
        $runtimePath = $this->resolvePath($envPath);
        $dir = dirname($runtimePath);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('无法创建 SQLite 目录：'.$dir);
        }

        if (! is_file($runtimePath) && file_put_contents($runtimePath, '') === false) {
            throw new RuntimeException('无法创建 SQLite 数据库文件：'.$runtimePath);
        }

        if (! is_writable($runtimePath)) {
            throw new RuntimeException('SQLite 数据库文件不可写：'.$runtimePath);
        }

        return [
            'connection' => 'sqlite',
            'env' => [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => $envPath,
                'DB_HOST' => '',
                'DB_PORT' => '',
                'DB_USERNAME' => '',
                'DB_PASSWORD' => '',
            ],
            'runtime' => [
                'database' => $runtimePath,
            ],
        ];
    }

    private function writeEnvironment(array $data, array $database): void
    {
        $appKey = config('app.key');
        if (! is_string($appKey) || $appKey === '') {
            $appKey = 'base64:'.base64_encode(random_bytes(32));
        }

        $values = array_merge([
            'APP_NAME' => (string) $data['app_name'],
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => (string) $data['app_url'],
            'ADMIN_EMAIL' => (string) $data['admin_email'],
            'ADMIN_PASSWORD' => '',
        ], $database['env']);

        $this->writeEnvironmentValues($values);
    }

    private function writeEnvironmentValues(array $values): void
    {
        $path = $this->state->envPath();
        $source = is_file($path) ? $path : base_path('.env.example');
        $content = is_file($source) ? (string) file_get_contents($source) : '';

        foreach ($values as $key => $value) {
            $content = $this->setEnvValue($content, $key, (string) $value);
            $_ENV[$key] = (string) $value;
            $_SERVER[$key] = (string) $value;
        }

        if (file_put_contents($path, rtrim($content).PHP_EOL) === false) {
            throw new RuntimeException('无法写入 .env 配置文件：'.$path);
        }
    }

    private function applyRuntimeConfig(array $data, array $database): void
    {
        config([
            'app.name' => (string) $data['app_name'],
            'app.url' => (string) $data['app_url'],
            'database.default' => $database['connection'],
        ]);

        if ($database['connection'] === 'mysql') {
            foreach ($database['runtime'] as $key => $value) {
                config(['database.connections.mysql.'.$key => $value]);
            }

            DB::purge('mysql');

            return;
        }

        config(['database.connections.sqlite.database' => $database['runtime']['database']]);
        DB::purge('sqlite');
    }

    private function assertDatabaseConnection(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            throw new RuntimeException('数据库连接失败：'.$exception->getMessage(), 0, $exception);
        }
    }

    private function createAdmin(array $data): void
    {
        User::query()->updateOrCreate(
            ['email' => (string) $data['admin_email']],
            [
                'name' => (string) $data['admin_name'],
                'password' => (string) $data['admin_password'],
                'is_admin' => true,
                'role' => 'owner',
                'permissions_json' => ['admins.manage'],
                'status' => 'active',
            ],
        );

        Setting::query()->updateOrCreate(
            ['group' => 'admin', 'key' => 'password_hash'],
            [
                'value' => Hash::make((string) $data['admin_password']),
                'type' => 'string',
                'description' => '安装向导创建的管理员密码 hash',
            ],
        );
    }

    private function tryStorageLink(): void
    {
        try {
            Artisan::call('storage:link');
        } catch (\Throwable $exception) {
            //
        }
    }

    private function runArtisan(string $command, array $parameters = []): void
    {
        $exitCode = Artisan::call($command, $parameters);

        if ($exitCode !== 0) {
            $output = trim(Artisan::output());
            throw new RuntimeException($output !== '' ? $output : $command.' 执行失败');
        }
    }

    private function setEnvValue(string $content, string $key, string $value): string
    {
        $line = $key.'='.$this->formatEnvValue($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $line, $content) ?? $content;
        }

        return rtrim($content).PHP_EOL.$line.PHP_EOL;
    }

    private function formatEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_\\.\\/:@-]+$/', $value)) {
            return $value;
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function resolvePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
