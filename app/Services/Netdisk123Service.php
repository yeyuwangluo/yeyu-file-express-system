<?php

namespace App\Services;

use App\Support\XiaoxinFileExpressSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Netdisk123Service
{
    private string $cookie;
    private string $token;
    private string $baseUrl = 'https://www.123pan.com/b/api';

    public function __construct()
    {
        $config = XiaoxinFileExpressSettings::netdisk123();
        $this->cookie = $config['cookie'] ?? '';
        $this->token = $config['token'] ?? '';
    }

    private function signPath(string $path, string $os = 'web', string $version = '3'): string
    {
        $table = ['a', 'd', 'e', 'f', 'g', 'h', 'l', 'm', 'y', 'i', 'j', 'n', 'o', 'p', 'k', 'q', 'r', 's', 't', 'u', 'b', 'c', 'v', 'w', 's', 'z'];
        $random = (string)round(1e7 * mt_rand() / mt_getrandmax());
        $now = time() + 8 * 3600; 
        
        $timestamp = (string)$now;
        $nowStr = date('YmdHi', $now);
        
        $encoded = '';
        for ($i = 0; $i < strlen($nowStr); $i++) {
            $digit = (int)$nowStr[$i];
            $encoded .= $table[$digit];
        }
        
        $timeSign = (string)crc32($encoded);
        $data = implode('|', [$timestamp, $random, $path, $os, $version, $timeSign]);
        $dataSign = (string)crc32($data);
        
        return implode('-', [$timestamp, $random, $dataSign]);
    }

    private function getApiUrl(string $path): string
    {
        $signedParams = $this->signPath($path);
        return $this->baseUrl . $path . '?auth-key=' . $signedParams;
    }

    private function getHeaders(): array
    {
        return [
            'Cookie' => $this->cookie,
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Origin' => 'https://www.123pan.com',
            'Referer' => 'https://www.123pan.com/',
            'Platform' => 'web',
            'App-Version' => '3',
        ];
    }

    public function uploadFile(string $filePath, string $fileName, string $parentFileId = '0'): array
    {
        try {
            if (empty($this->cookie) || empty($this->token)) {
                return ['success' => false, 'error' => '123网盘配置不完整'];
            }

            if (!file_exists($filePath)) {
                return ['success' => false, 'error' => '文件不存在'];
            }

            $fileSize = filesize($filePath);
            $fileContent = file_get_contents($filePath);
            $md5 = md5($fileContent);

            Log::info('123网盘上传开始', ['file' => $fileName, 'size' => $fileSize]);

            // 步骤1: 请求上传
            $uploadRequestUrl = $this->getApiUrl('/file/upload_request');
            $requestData = [
                'driveId' => 0,
                'duplicate' => 2,
                'etag' => $md5,
                'fileName' => $fileName,
                'parentFileId' => $parentFileId,
                'size' => $fileSize,
                'type' => 0,
            ];

            Log::info('步骤1: 请求上传', ['url' => $uploadRequestUrl]);

            $response = Http::withHeaders($this->getHeaders())
                ->withOptions(['verify' => false])
                ->post($uploadRequestUrl, $requestData);

            if (!$response->successful()) {
                Log::error('步骤1失败', ['status' => $response->status()]);
                return ['success' => false, 'error' => '上传请求失败'];
            }

            $uploadData = $response->json();
            Log::info('步骤1响应', ['data' => $uploadData]);

            // 检查是否秒传
            if (isset($uploadData['data']['Reuse']) && $uploadData['data']['Reuse']) {
                return ['success' => true, 'data' => $uploadData, 'message' => '秒传成功'];
            }

            $fileId = $uploadData['data']['FileId'] ?? '';
            $uploadId = $uploadData['data']['UploadId'] ?? '';
            $key = $uploadData['data']['Key'] ?? '';
            $bucket = $uploadData['data']['Bucket'] ?? '';
            $storageNode = $uploadData['data']['StorageNode'] ?? '';
            $sliceSize = (int)($uploadData['data']['SliceSize'] ?? 16777216);

            if (empty($fileId) || empty($uploadId) || empty($key) || empty($bucket) || empty($storageNode)) {
                Log::error('上传信息不完整', ['data' => $uploadData]);
                return ['success' => false, 'error' => '上传信息不完整'];
            }

            Log::info('上传信息', ['fileId' => $fileId, 'uploadId' => $uploadId, 'key' => $key, 'bucket' => $bucket]);

            // 步骤2: 获取上传URL
            $authUrl = $this->getApiUrl('/file/s3_upload_object/auth');
            $authData = [
                'StorageNode' => $storageNode,
                'bucket' => $bucket,
                'key' => $key,
                'partNumberStart' => 1,
                'partNumberEnd' => 1,
                'uploadId' => $uploadId,
            ];

            Log::info('步骤2: 获取上传URL', ['url' => $authUrl, 'data' => $authData]);

            $authResponse = Http::withHeaders($this->getHeaders())
                ->withOptions(['verify' => false])
                ->post($authUrl, $authData);

            if (!$authResponse->successful()) {
                Log::error('步骤2失败', ['status' => $authResponse->status()]);
                return ['success' => false, 'error' => '获取上传URL失败'];
            }

            $authResponseData = $authResponse->json();
            Log::info('步骤2响应', ['data' => $authResponseData]);

            $preSignedUrls = $authResponseData['data']['presignedUrls'] ?? [];

            if (empty($preSignedUrls['1'])) {
                Log::error('预签名URL为空', ['data' => $authResponseData]);
                return ['success' => false, 'error' => '预签名URL为空'];
            }

            $uploadUrl = $preSignedUrls['1'];
            Log::info('上传URL', ['url' => $uploadUrl]);

            // 步骤3: 上传文件
            Log::info('步骤3: 上传文件', ['size' => $fileSize]);

            $uploadResponse = Http::withOptions(['verify' => false])
                ->attach('file', $fileContent, $fileName)
                ->put($uploadUrl);

            if (!$uploadResponse->successful()) {
                Log::error('步骤3失败', ['status' => $uploadResponse->status()]);
                return ['success' => false, 'error' => '文件上传失败'];
            }

            Log::info('步骤3成功', ['status' => $uploadResponse->status()]);

            // 步骤4: 完成上传
            $completeUrl = $this->getApiUrl('/file/upload_complete/v2');
            $completeData = [
                'StorageNode' => $storageNode,
                'bucket' => $bucket,
                'fileId' => $fileId,
                'fileSize' => $fileSize,
                'isMultipart' => false,
                'key' => $key,
                'uploadId' => $uploadId,
            ];

            Log::info('步骤4: 完成上传', ['url' => $completeUrl, 'data' => $completeData]);

            $completeResponse = Http::withHeaders(array_merge($this->getHeaders(), ['Content-Type' => 'application/json']))
                ->withOptions(['verify' => false])
                ->post($completeUrl, $completeData);

            Log::info('步骤4响应', ['status' => $completeResponse->status()]);

            if ($completeResponse->successful()) {
                Log::info('上传成功', ['fileId' => $fileId]);
                return ['success' => true, 'data' => array_merge($uploadData, ['fileId' => $fileId]), 'message' => '上传成功'];
            }

            Log::error('步骤4失败', ['status' => $completeResponse->status()]);
            return ['success' => false, 'error' => '完成上传失败'];

        } catch (\Exception $e) {
            Log::error('上传异常', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => '上传异常'];
        }
    }

    public function testConnection(): bool
    {
        try {
            if (empty($this->cookie) || empty($this->token)) {
                return false;
            }

            $userUrl = $this->getApiUrl('/user/info');
            $response = Http::withHeaders($this->getHeaders())
                ->withOptions(['verify' => false])
                ->get($userUrl);

            return $response->successful();

        } catch (\Exception $e) {
            return false;
        }
    }
}
