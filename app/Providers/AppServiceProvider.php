<?php

namespace App\Providers;

use App\Support\XiaoxinFileExpressSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureStorageDisks();

        RateLimiter::for('uploads', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
        ]);

        RateLimiter::for('downloads', fn (Request $request) => [
            Limit::perMinute(120)->by($request->ip()),
        ]);
    }

    private function configureStorageDisks(): void
    {
        try {
            $storage = XiaoxinFileExpressSettings::storage();
        } catch (\Throwable) {
            return;
        }

        $default = in_array($storage['defaultDisk'] ?? 'local', ['local', 'oss', 'tencent', 's3'], true)
            ? $storage['defaultDisk']
            : 'local';

        config([
            'filesystems.default' => $default,
            'filesystems.disks.oss.key' => $storage['ossAccessKeyId'] ?? null,
            'filesystems.disks.oss.secret' => $storage['ossAccessKeySecret'] ?? null,
            'filesystems.disks.oss.region' => $storage['ossRegion'] ?? null,
            'filesystems.disks.oss.bucket' => $storage['ossBucket'] ?? null,
            'filesystems.disks.oss.endpoint' => $storage['ossEndpoint'] ?? null,
            'filesystems.disks.oss.url' => $storage['ossUrl'] ?? null,
            'filesystems.disks.tencent.key' => $storage['tencentSecretId'] ?? null,
            'filesystems.disks.tencent.secret' => $storage['tencentSecretKey'] ?? null,
            'filesystems.disks.tencent.region' => $storage['tencentRegion'] ?? null,
            'filesystems.disks.tencent.bucket' => $storage['tencentBucket'] ?? null,
            'filesystems.disks.tencent.endpoint' => $storage['tencentEndpoint'] ?? null,
            'filesystems.disks.tencent.url' => $storage['tencentUrl'] ?? null,
            'filesystems.disks.s3.key' => $storage['s3AccessKeyId'] ?? null,
            'filesystems.disks.s3.secret' => $storage['s3SecretAccessKey'] ?? null,
            'filesystems.disks.s3.region' => $storage['s3Region'] ?? null,
            'filesystems.disks.s3.bucket' => $storage['s3Bucket'] ?? null,
            'filesystems.disks.s3.endpoint' => $storage['s3Endpoint'] ?? null,
            'filesystems.disks.s3.url' => $storage['s3Url'] ?? null,
        ]);
    }
}
