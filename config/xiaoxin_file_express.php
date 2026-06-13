<?php

$installerInstalled = env('XIAOXIN_FILE_EXPRESS_INSTALLED');
if ($installerInstalled === '') {
    $installerInstalled = null;
}

return [
    'installer' => [
        'installed' => $installerInstalled === null
            ? null
            : filter_var($installerInstalled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
        'env_path' => env('XIAOXIN_FILE_EXPRESS_ENV_PATH'),
        'marker_path' => env('XIAOXIN_FILE_EXPRESS_MARKER_PATH'),
    ],

    'upload' => [
        'max_file_size' => (int) env('UPLOAD_MAX_FILE_SIZE', 52_428_800),
        'default_expire_days' => (int) env('UPLOAD_DEFAULT_EXPIRE_DAYS', 1),
        'max_expire_days' => (int) env('UPLOAD_MAX_EXPIRE_DAYS', 7),
        'allowed_file_types' => env('UPLOAD_ALLOWED_FILE_TYPES', 'jpg,jpeg,png,gif,webp,avif,bmp,ico,livp,mp3,wav,flac,m4a,aac,ogg,opus,mp4,webm,mov,m4v,pdf,txt,md,json,csv,doc,docx,xls,xlsx,ppt,pptx,zip,rar,7z,tar,gz,apk'),
        'extract_code_min' => 4,
        'extract_code_max' => 6,
    ],

    'chunked_upload' => [
        'enabled' => (bool) env('CHUNKED_UPLOAD_ENABLED', true),
        'max_chunk_size' => (int) env('CHUNKED_UPLOAD_MAX_CHUNK_SIZE', 10_485_760),
        'max_chunks' => (int) env('CHUNKED_UPLOAD_MAX_CHUNKS', 10_000),
        'session_ttl_minutes' => (int) env('CHUNKED_UPLOAD_TTL_MINUTES', 120),
    ],

    'risk' => [
        'block_score' => (int) env('RISK_BLOCK_SCORE', 90),
        'repeated_failure_window_minutes' => (int) env('RISK_FAILURE_WINDOW_MINUTES', 60),
        'repeated_failure_limit' => (int) env('RISK_FAILURE_LIMIT', 5),
    ],

    'security' => [
        'virus_scan' => [
            'enabled' => (bool) env('VIRUS_SCAN_ENABLED', false),
            'clamav_path' => env('CLAMAV_PATH', 'clamscan'),
            'timeout_seconds' => (int) env('VIRUS_SCAN_TIMEOUT_SECONDS', 60),
        ],
    ],

    'geetest' => [
        'enabled' => (bool) env('GEETEST_ENABLED', false),
        'captcha_id' => env('GEETEST_CAPTCHA_ID', 'db153122426093b5f390c4d1ece1a981'),
    ],

    'footer' => [
        'icp_beian' => env('FOOTER_ICP_BEIAN', '青ICP备2026000265号-1'),
        'gongan_beian' => env('FOOTER_GONGAN_BEIAN', '青公网安备63012302000007号'),
        'gongan_code' => env('FOOTER_GONGAN_CODE', '63012302000007'),
        'links' => [
            ['id' => 'terms', 'type' => 'link', 'text' => '用户协议与隐私政策', 'href' => '/terms', 'meta' => '', 'icon' => '', 'enabled' => true, 'sort' => 10],
            ['id' => 'status', 'type' => 'link', 'text' => '服务状态', 'href' => '/status', 'meta' => '', 'icon' => '', 'enabled' => true, 'sort' => 20],
        ],
    ],

    'lan_transfer' => [
        'enabled' => (bool) env('LAN_TRANSFER_ENABLED', true),
        'max_file_size' => (int) env('LAN_MAX_FILE_SIZE', 2_147_483_648),
        'max_file_count' => (int) env('LAN_MAX_FILE_COUNT', 5),
        'max_total_size_enabled' => (bool) env('LAN_MAX_TOTAL_SIZE_ENABLED', false),
        'max_total_size' => (int) env('LAN_MAX_TOTAL_SIZE', 21_474_836_480),
        'allowed_file_types' => env('LAN_ALLOWED_FILE_TYPES', ''),
        'expire_minutes' => (int) env('LAN_EXPIRE_MINUTES', 10),
        'completed_retention_minutes' => (int) env('LAN_COMPLETED_RETENTION_MINUTES', 860),
        'text_enabled' => (bool) env('LAN_TEXT_ENABLED', true),
        'text_max_length' => (int) env('LAN_TEXT_MAX_LENGTH', 15_000),
        'text_max_lines' => (int) env('LAN_TEXT_MAX_LINES', 500),
        'text_allow_title' => (bool) env('LAN_TEXT_ALLOW_TITLE', true),
        'text_retention_minutes' => (int) env('LAN_TEXT_RETENTION_MINUTES', 1_440),
    ],

    'app_download' => [
        'enabled' => false,
        'title' => '叶宇文件快递 App',
        'subtitle' => '随时随地，快速分享',
        'description' => '下载入口开放后会在这里提供正式版本',
        'features' => ['快速上传下载', '安全加密传输', '离线文件管理', '扫码快速获取'],
        'android_enabled' => false,
        'android_download_url' => '',
        'android_version' => '',
        'ios_enabled' => false,
        'ios_download_url' => '',
        'ios_version' => '',
        'qrcode_enabled' => false,
    ],

    'storage_limit' => (int) env('STORAGE_LIMIT_BYTES', 107_374_182_400),
];
