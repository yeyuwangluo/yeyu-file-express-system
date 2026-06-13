<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'oss' => [
            'driver' => 's3',
            'key' => env('OSS_ACCESS_KEY_ID'),
            'secret' => env('OSS_ACCESS_KEY_SECRET'),
            'region' => env('OSS_REGION', 'oss-cn-hangzhou'),
            'bucket' => env('OSS_BUCKET'),
            'endpoint' => env('OSS_ENDPOINT'),
            'url' => env('OSS_URL'),
            'use_path_style_endpoint' => env('OSS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'aliyun' => [
            'driver' => 's3',
            'key' => env('ALIYUN_ACCESS_KEY_ID'),
            'secret' => env('ALIYUN_ACCESS_KEY_SECRET'),
            'region' => env('ALIYUN_REGION', 'oss-cn-hangzhou'),
            'bucket' => env('ALIYUN_BUCKET'),
            'endpoint' => env('ALIYUN_ENDPOINT'),
            'url' => env('ALIYUN_URL'),
            'use_path_style_endpoint' => true,
            'throw' => false,
        ],

        'tencent' => [
            'driver' => 's3',
            'key' => env('TENCENT_COS_SECRET_ID'),
            'secret' => env('TENCENT_COS_SECRET_KEY'),
            'region' => env('TENCENT_COS_REGION'),
            'bucket' => env('TENCENT_COS_BUCKET'),
            'endpoint' => env('TENCENT_COS_ENDPOINT'),
            'url' => env('TENCENT_COS_URL'),
            'use_path_style_endpoint' => true,
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'netdisk123' => [
            'driver' => 'netdisk123',
            'cookie' => env('NETDISK123_COOKIE'),
            'token' => env('NETDISK123_TOKEN'),
            'base_url' => env('NETDISK123_BASE_URL'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
