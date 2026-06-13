<?php

namespace App\Support;

use App\Models\Setting;

class XiaoxinFileExpressSettings
{
    public static function configPayload(): array
    {
        return [
            'upload' => self::upload(),
            'storage' => self::publicStorage(),
            'chunkedUpload' => self::chunkedUpload(),
            'risk' => self::risk(),
            'virusScan' => self::virusScan(),
            'geetest' => self::geetest(),
            'footer' => self::footer(),
            'lanTransfer' => self::lanTransfer(),
            'netdisk123' => self::publicNetdisk123(),
        ];
    }

    public static function upload(): array
    {
        return [
            'maxFileSize' => Setting::valueFor('upload', 'max_file_size', config('xiaoxin_file_express.upload.max_file_size')),
            'maxExpireDays' => Setting::valueFor('upload', 'max_expire_days', config('xiaoxin_file_express.upload.max_expire_days')),
            'defaultExpireDays' => Setting::valueFor('upload', 'default_expire_days', config('xiaoxin_file_express.upload.default_expire_days')),
            'allowedFileTypes' => Setting::valueFor('upload', 'allowed_file_types', config('xiaoxin_file_express.upload.allowed_file_types')),
        ];
    }

    public static function storage(): array
    {
        return [
            'defaultDisk' => Setting::valueFor('storage', 'default_disk', 'local'),
            'ossEnabled' => (bool)Setting::valueFor('storage', 'oss_enabled', '0'),
            'ossAccessKeyId' => Setting::valueFor('storage', 'oss_access_key_id', env('OSS_ACCESS_KEY_ID')),
            'ossAccessKeySecret' => Setting::valueFor('storage', 'oss_access_key_secret', env('OSS_ACCESS_KEY_SECRET')),
            'ossRegion' => Setting::valueFor('storage', 'oss_region', env('OSS_REGION', 'oss-cn-hangzhou')),
            'ossBucket' => Setting::valueFor('storage', 'oss_bucket', env('OSS_BUCKET')),
            'ossEndpoint' => Setting::valueFor('storage', 'oss_endpoint', env('OSS_ENDPOINT')),
            'ossUrl' => Setting::valueFor('storage', 'oss_url', env('OSS_URL')),
            'tencentEnabled' => (bool)Setting::valueFor('storage', 'tencent_enabled', '0'),
            'tencentSecretId' => Setting::valueFor('storage', 'tencent_secret_id', env('TENCENT_COS_SECRET_ID')),
            'tencentSecretKey' => Setting::valueFor('storage', 'tencent_secret_key', env('TENCENT_COS_SECRET_KEY')),
            'tencentRegion' => Setting::valueFor('storage', 'tencent_region', env('TENCENT_COS_REGION')),
            'tencentBucket' => Setting::valueFor('storage', 'tencent_bucket', env('TENCENT_COS_BUCKET')),
            'tencentEndpoint' => Setting::valueFor('storage', 'tencent_endpoint', env('TENCENT_COS_ENDPOINT')),
            'tencentUrl' => Setting::valueFor('storage', 'tencent_url', env('TENCENT_COS_URL')),
            's3Enabled' => (bool)Setting::valueFor('storage', 's3_enabled', '0'),
            's3AccessKeyId' => Setting::valueFor('storage', 's3_access_key_id', env('AWS_ACCESS_KEY_ID')),
            's3SecretAccessKey' => Setting::valueFor('storage', 's3_secret_access_key', env('AWS_SECRET_ACCESS_KEY')),
            's3Region' => Setting::valueFor('storage', 's3_region', env('AWS_DEFAULT_REGION', 'us-east-1')),
            's3Bucket' => Setting::valueFor('storage', 's3_bucket', env('AWS_BUCKET')),
            's3Endpoint' => Setting::valueFor('storage', 's3_endpoint', env('AWS_ENDPOINT')),
            's3Url' => Setting::valueFor('storage', 's3_url', env('AWS_URL')),
        ];
    }

    public static function publicStorage(): array
    {
        $storage = self::storage();

        return [
            'defaultDisk' => $storage['defaultDisk'],
            'ossEnabled' => $storage['ossEnabled'],
            'ossBucket' => $storage['ossBucket'],
            'ossRegion' => $storage['ossRegion'],
            'ossEndpoint' => $storage['ossEndpoint'],
            'ossUrl' => $storage['ossUrl'],
            'tencentEnabled' => $storage['tencentEnabled'],
            'tencentRegion' => $storage['tencentRegion'],
            'tencentBucket' => $storage['tencentBucket'],
            'tencentEndpoint' => $storage['tencentEndpoint'],
            'tencentUrl' => $storage['tencentUrl'],
            's3Enabled' => $storage['s3Enabled'],
            's3Region' => $storage['s3Region'],
            's3Bucket' => $storage['s3Bucket'],
            's3Endpoint' => $storage['s3Endpoint'],
            's3Url' => $storage['s3Url'],
        ];
    }
    public static function geetest(): array
    {
        return [
            'enabled' => Setting::valueFor('geetest', 'enabled', config('xiaoxin_file_express.geetest.enabled')),
            'captchaId' => Setting::valueFor('geetest', 'captcha_id', config('xiaoxin_file_express.geetest.captcha_id')),
        ];
    }

    public static function chunkedUpload(): array
    {
        return [
            'enabled' => Setting::valueFor('chunked_upload', 'enabled', config('xiaoxin_file_express.chunked_upload.enabled')),
            'maxChunkSize' => Setting::valueFor('chunked_upload', 'max_chunk_size', config('xiaoxin_file_express.chunked_upload.max_chunk_size')),
            'maxChunks' => Setting::valueFor('chunked_upload', 'max_chunks', config('xiaoxin_file_express.chunked_upload.max_chunks')),
            'sessionTtlMinutes' => Setting::valueFor('chunked_upload', 'session_ttl_minutes', config('xiaoxin_file_express.chunked_upload.session_ttl_minutes')),
        ];
    }

    public static function risk(): array
    {
        return [
            'blockScore' => Setting::valueFor('risk', 'block_score', config('xiaoxin_file_express.risk.block_score')),
        ];
    }

    public static function virusScan(): array
    {
        return [
            'enabled' => Setting::valueFor('virus_scan', 'enabled', config('xiaoxin_file_express.security.virus_scan.enabled')),
            'clamavPath' => Setting::valueFor('virus_scan', 'clamav_path', config('xiaoxin_file_express.security.virus_scan.clamav_path')),
            'timeoutSeconds' => Setting::valueFor('virus_scan', 'timeout_seconds', config('xiaoxin_file_express.security.virus_scan.timeout_seconds')),
        ];
    }

    public static function footer(): array
    {
        $icp = Setting::valueFor('footer', 'icp_beian', config('xiaoxin_file_express.footer.icp_beian'));
        $gongan = Setting::valueFor('footer', 'gongan_beian', config('xiaoxin_file_express.footer.gongan_beian'));
        $gonganCode = Setting::valueFor('footer', 'gongan_code', config('xiaoxin_file_express.footer.gongan_code'));
        $links = Setting::valueFor('footer', 'links', config('xiaoxin_file_express.footer.links', []));

        return [
            'items' => collect([
                ['id' => 'qing-icp', 'type' => 'icp', 'text' => $icp, 'href' => 'https://beian.miit.gov.cn/', 'meta' => '', 'icon' => '', 'enabled' => true, 'sort' => 0],
                ['id' => 'qing-gongan', 'type' => 'gongan', 'text' => $gongan, 'href' => '', 'meta' => $gonganCode, 'icon' => '', 'enabled' => true, 'sort' => 1],
                ...(is_array($links) ? $links : []),
            ])->filter(fn (array $item): bool => (bool) ($item['enabled'] ?? true))
                ->sortBy(fn (array $item): int => (int) ($item['sort'] ?? 0))
                ->values()
                ->all(),
            'icpBeian' => $icp,
            'gonganBeian' => $gongan,
            'gonganCode' => $gonganCode,
            'links' => is_array($links) ? $links : [],
        ];
    }

    public static function lanTransfer(): array
    {
        return [
            'enabled' => Setting::valueFor('lan_transfer', 'enabled', config('xiaoxin_file_express.lan_transfer.enabled')),
            'maxFileSize' => Setting::valueFor('lan_transfer', 'max_file_size', config('xiaoxin_file_express.lan_transfer.max_file_size')),
            'maxFileCount' => Setting::valueFor('lan_transfer', 'max_file_count', config('xiaoxin_file_express.lan_transfer.max_file_count')),
            'maxTotalSizeEnabled' => Setting::valueFor('lan_transfer', 'max_total_size_enabled', config('xiaoxin_file_express.lan_transfer.max_total_size_enabled')),
            'maxTotalSize' => Setting::valueFor('lan_transfer', 'max_total_size', config('xiaoxin_file_express.lan_transfer.max_total_size')),
            'allowedFileTypes' => Setting::valueFor('lan_transfer', 'allowed_file_types', config('xiaoxin_file_express.lan_transfer.allowed_file_types')),
            'expireMinutes' => Setting::valueFor('lan_transfer', 'expire_minutes', config('xiaoxin_file_express.lan_transfer.expire_minutes')),
            'completedRetentionMinutes' => Setting::valueFor('lan_transfer', 'completed_retention_minutes', config('xiaoxin_file_express.lan_transfer.completed_retention_minutes')),
            'textEnabled' => Setting::valueFor('lan_transfer', 'text_enabled', config('xiaoxin_file_express.lan_transfer.text_enabled')),
            'textMaxLength' => Setting::valueFor('lan_transfer', 'text_max_length', config('xiaoxin_file_express.lan_transfer.text_max_length')),
            'textMaxLines' => Setting::valueFor('lan_transfer', 'text_max_lines', config('xiaoxin_file_express.lan_transfer.text_max_lines')),
            'textAllowTitle' => Setting::valueFor('lan_transfer', 'text_allow_title', config('xiaoxin_file_express.lan_transfer.text_allow_title')),
            'textRetentionMinutes' => Setting::valueFor('lan_transfer', 'text_retention_minutes', config('xiaoxin_file_express.lan_transfer.text_retention_minutes')),
        ];
    }


    public static function netdisk123(): array
    {
        return [
            'enabled' => Setting::valueFor('netdisk123', 'enabled', false),
            'cookie' => Setting::valueFor('netdisk123', 'cookie', env('NETDISK123_COOKIE')),
            'token' => Setting::valueFor('netdisk123', 'token', env('NETDISK123_TOKEN')),
            'username' => Setting::valueFor('netdisk123', 'username', env('NETDISK123_USERNAME')),
            'maxFileSize' => Setting::valueFor('netdisk123', 'max_file_size', 104857600),
            'autoShare' => Setting::valueFor('netdisk123', 'auto_share', true),
            'shareExpireDays' => Setting::valueFor('netdisk123', 'share_expire_days', 7),
        ];
    }

    public static function publicNetdisk123(): array
    {
        $netdisk123 = self::netdisk123();

        return [
            'enabled' => $netdisk123['enabled'],
            'maxFileSize' => $netdisk123['maxFileSize'],
            'autoShare' => $netdisk123['autoShare'],
            'shareExpireDays' => $netdisk123['shareExpireDays'],
        ];
    }
    public static function appDownload(): array
    {
        return [
            'enabled' => Setting::valueFor('app_download', 'enabled', config('xiaoxin_file_express.app_download.enabled')),
            'title' => Setting::valueFor('app_download', 'title', config('xiaoxin_file_express.app_download.title')),
            'subtitle' => Setting::valueFor('app_download', 'subtitle', config('xiaoxin_file_express.app_download.subtitle')),
            'description' => Setting::valueFor('app_download', 'description', config('xiaoxin_file_express.app_download.description')),
            'features' => Setting::valueFor('app_download', 'features', config('xiaoxin_file_express.app_download.features')),
            'androidEnabled' => Setting::valueFor('app_download', 'android_enabled', config('xiaoxin_file_express.app_download.android_enabled')),
            'androidDownloadUrl' => Setting::valueFor('app_download', 'android_download_url', config('xiaoxin_file_express.app_download.android_download_url')),
            'androidVersion' => Setting::valueFor('app_download', 'android_version', config('xiaoxin_file_express.app_download.android_version')),
            'iosEnabled' => Setting::valueFor('app_download', 'ios_enabled', config('xiaoxin_file_express.app_download.ios_enabled')),
            'iosDownloadUrl' => Setting::valueFor('app_download', 'ios_download_url', config('xiaoxin_file_express.app_download.ios_download_url')),
            'iosVersion' => Setting::valueFor('app_download', 'ios_version', config('xiaoxin_file_express.app_download.ios_version')),
            'qrcodeEnabled' => Setting::valueFor('app_download', 'qrcode_enabled', config('xiaoxin_file_express.app_download.qrcode_enabled')),
        ];
    }
}
