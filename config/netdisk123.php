<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 123网盘配置
    |--------------------------------------------------------------------------
    */

    'enabled' => env('NETDISK123_ENABLED', false),

    'cookie' => env('NETDISK123_COOKIE', ''),

    'token' => env('NETDISK123_TOKEN', ''),

    'username' => env('NETDISK123_USERNAME', ''),

    'password' => env('NETDISK123_PASSWORD', ''),

    'api_base_url' => env('NETDISK123_API_URL', 'https://www.123pan.com/api'),

    'max_file_size' => env('NETDISK123_MAX_FILE_SIZE', 104857600), // 100MB

    'auto_share' => env('NETDISK123_AUTO_SHARE', true),

    'share_expire_days' => env('NETDISK123_SHARE_EXPIRE_DAYS', 7),

];
