<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class AIScanner
{
    private array $config;
    private bool $enabled;

    public function __construct()
    {
        $this->config = [
            'enabled' => Setting::valueFor('ai_scan', 'ai_scan_enabled', false),
            'api_url' => Setting::valueFor('ai_scan', 'ai_scan_api_url', ''),
            'api_key' => Setting::valueFor('ai_scan', 'ai_scan_api_key', ''),
            'model' => Setting::valueFor('ai_scan', 'ai_scan_model', 'gpt-4'),
            'timeout' => Setting::valueFor('ai_scan', 'ai_scan_timeout', 30),
            'max_file_size' => Setting::valueFor('ai_scan', 'ai_scan_max_file_size', 102400),
        ];
        
        $this->enabled = $this->config['enabled'] && !empty($this->config['api_key']);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function scanFile(string $content, string $filename, string $filepath = ''): array
    {
        if (!$this->enabled) {
            return $this->skipResult('AI扫描未启用');
        }

        if (strlen($content) > $this->config['max_file_size']) {
            return $this->skipResult('文件过大，超过AI扫描限制');
        }

        try {
            $safeContent = $this->sanitizeForPrompt($content);
            $safeFilename = $this->sanitizeForPrompt($filename);
            $prompt = $this->buildPrompt($safeContent, $safeFilename);
            $response = $this->callAI($prompt);
            return $this->parseResponse($response, $safeFilename);
        } catch (\Exception $e) {
            Log::error('AI扫描失败: ' . $e->getMessage());
            return $this->skipResult('AI扫描失败: ' . $e->getMessage());
        }
    }

    public function scanMediaFile(string $filepath, string $filename, string $mimeType = ''): array
    {
        if (!$this->enabled) {
            return $this->skipResult('AI媒体识别未启用');
        }

        try {
            $dataUrls = $this->mediaDataUrls($filepath, $mimeType);
            if ($dataUrls === []) {
                return $this->skipResult('媒体文件无法生成可识别预览');
            }

            $safeFilename = $this->sanitizeForPrompt($filename);
            $prompt = $this->buildMediaPrompt($safeFilename, $mimeType);
            $response = $this->callVisionAI($prompt, $dataUrls);

            return $this->parseResponse($response, $safeFilename);
        } catch (\Exception $e) {
            Log::error('AI媒体识别失败: ' . $e->getMessage());

            return [
                'is_malicious' => true,
                'threat_type' => 'media_review_failed',
                'confidence' => '中',
                'reason' => '媒体内容识别失败，按安全策略临时拦截: ' . $e->getMessage(),
                'suspicious_code' => '',
                'scanner' => 'ai_media',
                'model' => $this->config['model'],
                'scanned_at' => now()->toIso8601String(),
                'skipped' => false,
            ];
        }
    }

    private function sanitizeForPrompt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, GB18030, GBK, BIG5, ISO-8859-1');
            $value = is_string($converted) ? $converted : @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        }

        $value = is_string($value) ? $value : '';
        $value = preg_replace('/[^\P{C}\t\r\n]/u', '', $value);

        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            $value = @iconv('UTF-8', 'UTF-8//IGNORE', (string) $value) ?: '';
        }

        return $value;
    }

    private function buildPrompt(string $content, string $filename): string
    {
        return "请分析以下文件内容是否包含恶意代码、后门、webshell或其他安全威胁。

文件名: " . $filename . "

文件内容:
```php
" . $content . "
```

请以JSON格式返回分析结果，格式如下:
{
    \"is_malicious\": true or false,
    \"threat_type\": \"威胁类型(如: webshell, backdoor, obfuscated_code, safe等)\",
    \"confidence\": \"置信度(高/中/低)\",
    \"reason\": \"判断理由\",
    \"suspicious_code\": \"可疑代码片段\"
}

只返回JSON，不要其他内容。";
    }

    private function buildMediaPrompt(string $filename, string $mimeType): string
    {
        return "请审核这个媒体文件是否包含违规内容。\n\n文件名: {$filename}\nMIME: {$mimeType}\n\n违规内容包括：色情或裸露、未成年人性相关内容、血腥暴力、极端暴力、恐怖主义或极端主义宣传、毒品违法交易、明显违法活动、仇恨或骚扰内容。\n\n请以JSON格式返回分析结果：\n{\n  \"is_malicious\": true or false,\n  \"threat_type\": \"违规类型，如 adult_content, minor_abuse, gore, violence, extremist, illegal_activity, hate, safe\",\n  \"confidence\": \"高/中/低\",\n  \"reason\": \"判断理由\",\n  \"suspicious_code\": \"可留空\"\n}\n\n只返回JSON，不要其他内容。";
    }

    private function callAI(string $prompt): string
    {
        $apiUrl = $this->config['api_url'];
        $apiKey = $this->config['api_key'];
        $model = $this->config['model'];

        if (str_contains($apiUrl, 'openai.com') || str_contains($apiUrl, 'api.openai')) {
            return $this->callOpenAI($prompt, $apiKey, $model);
        } elseif (str_contains($apiUrl, 'anthropic.com') || str_contains($apiUrl, 'api.anthropic')) {
            return $this->callClaude($prompt, $apiKey, $model);
        } else {
            return $this->callCustomAPI($prompt, $apiKey, $model);
        }
    }

    private function callOpenAI(string $prompt, string $apiKey, string $model): string
    {
        $response = Http::timeout($this->config['timeout'])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => '你是一个专业的安全分析师，擅长识别恶意代码。'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.1,
            ]);

        if (!$response->successful()) {
            throw new \Exception('AI API调用失败: ' . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    private function callClaude(string $prompt, string $apiKey, string $model): string
    {
        $response = Http::timeout($this->config['timeout'])
            ->withHeaders([
                'x-api-key' => $apiKey,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $model,
                'max_tokens' => 1024,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (!$response->successful()) {
            throw new \Exception('AI API调用失败: ' . $response->body());
        }

        return $response->json('content.0.text');
    }

    private function callCustomAPI(string $prompt, string $apiKey, string $model): string
    {
        $response = Http::timeout($this->config['timeout'])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->config['api_url'], [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => '你是一个专业的安全分析师，擅长识别恶意代码。'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.1,
            ]);

        if (!$response->successful()) {
            throw new \Exception('自定义AI API调用失败: ' . $response->body());
        }

        return $response->json('choices.0.message.content');
    }

    private function callVisionAI(string $prompt, array $dataUrls): string
    {
        $apiUrl = $this->config['api_url'];
        $apiKey = $this->config['api_key'];
        $model = $this->config['model'];

        if (str_contains($apiUrl, 'anthropic.com') || str_contains($apiUrl, 'api.anthropic')) {
            throw new \RuntimeException('当前媒体识别暂不支持 Anthropic 格式接口');
        }

        $endpoint = (str_contains($apiUrl, 'openai.com') || str_contains($apiUrl, 'api.openai'))
            ? 'https://api.openai.com/v1/chat/completions'
            : $apiUrl;

        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($dataUrls as $dataUrl) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]];
        }

        $response = Http::timeout($this->config['timeout'])
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => '你是一个专业的媒体内容安全审核员，只根据可见内容判断是否违规。'],
                    ['role' => 'user', 'content' => $content],
                ],
                'temperature' => 0,
            ]);

        if (!$response->successful()) {
            throw new \Exception('AI媒体识别API调用失败: ' . $response->body());
        }

        return (string) $response->json('choices.0.message.content');
    }

    private function mediaDataUrls(string $filepath, string $mimeType): array
    {
        $mimeType = strtolower($mimeType ?: (mime_content_type($filepath) ?: ''));

        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
            return [$this->imageDataUrl($filepath, $mimeType)];
        }

        if (str_starts_with($mimeType, 'video/')) {
            return $this->videoFrameDataUrls($filepath);
        }

        return [];
    }

    private function imageDataUrl(string $filepath, string $mimeType): string
    {
        if (!is_file($filepath) || filesize($filepath) === false || filesize($filepath) > 8 * 1024 * 1024) {
            throw new \RuntimeException('图片过大或不可读');
        }

        $content = file_get_contents($filepath);
        if ($content === false) {
            throw new \RuntimeException('图片读取失败');
        }

        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
    }

    private function videoFrameDataUrls(string $filepath): array
    {
        $dir = sys_get_temp_dir() . '/media_scan_' . bin2hex(random_bytes(8));
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('视频抽帧临时目录创建失败');
        }

        try {
            $pattern = $dir . '/frame_%02d.jpg';
            $process = new Process(['ffmpeg', '-y', '-i', $filepath, '-vf', 'fps=1/10,scale=640:-1', '-frames:v', '3', $pattern]);
            $process->setTimeout(max(10, (int) $this->config['timeout']));
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException('视频抽帧失败');
            }

            $urls = [];
            foreach (glob($dir . '/frame_*.jpg') ?: [] as $frame) {
                $urls[] = $this->imageDataUrl($frame, 'image/jpeg');
            }

            return $urls;
        } finally {
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($dir);
        }
    }

    private function skipResult(string $reason): array
    {
        return [
            'is_malicious' => false,
            'threat_type' => 'skipped',
            'confidence' => 'none',
            'reason' => $reason,
            'scanner' => 'ai',
            'skipped' => true,
        ];
    }

    private function parseResponse(string $response, string $filename): array
    {
        $jsonCandidates = [];

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $response, $matches)) {
            $jsonCandidates[] = $matches[1];
        }

        $start = strpos($response, '{');
        $end = strrpos($response, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $jsonCandidates[] = substr($response, $start, $end - $start + 1);
        }

        if (preg_match("/\{[^{}]*is_malicious[^{}]*\}/s", $response, $matches)) {
            $jsonCandidates[] = $matches[0];
        }

        foreach (array_unique($jsonCandidates) as $jsonStr) {
            $result = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($result["is_malicious"])) {
                return $this->formatResult($result, $filename);
            }
        }
        
        // 从自然语言中提取判断
        $isMalicious = false;
        $threatType = "unknown";
        $confidence = "低";
        $reason = "";
        
        if (preg_match("/(恶意|malicious|webshell| 后门|backdoor| 木马|trojan| 危险|dangerous| 威胁|threat)/u", $response)) {
            $isMalicious = true;
            
            if (preg_match("/webshell/i", $response) || preg_match("/后门/", $response)) {
                $threatType = "webshell";
                $confidence = "高";
            } elseif (preg_match("/木马 | trojan/i", $response)) {
                $threatType = "trojan";
                $confidence = "高";
            } else {
                $threatType = "suspicious";
                $confidence = "中";
            }
            
            if (preg_match("/(该文件 [^
。]*[。.])/u", $response, $reasonMatches)) {
                $reason = trim($reasonMatches[1]);
            } else {
                $reason = mb_substr($response, 0, 200);
            }
        }
        
        if (preg_match("/(安全|safe|正常|normal|benign|无害)/u", $response) && !$isMalicious) {
            $isMalicious = false;
            $threatType = "safe";
            $confidence = "中";
            $reason = "未检测到明显恶意特征";
        }
        
        return [
            "is_malicious" => $isMalicious,
            "threat_type" => $threatType,
            "confidence" => $confidence,
            "reason" => $reason ?: "自动分析结果",
            "suspicious_code" => "",
            "scanner" => "ai",
            "model" => $this->config["model"],
            "scanned_at" => now()->toIso8601String(),
            "skipped" => false,
        ];
    }
    
    private function formatResult(array $result, string $filename): array
    {
        return [
            "is_malicious" => $result["is_malicious"] ?? false,
            "threat_type" => $result["threat_type"] ?? "unknown",
            "confidence" => $result["confidence"] ?? "low",
            "reason" => $result["reason"] ?? "",
            "suspicious_code" => $result["suspicious_code"] ?? "",
            "scanner" => "ai",
            "model" => $this->config["model"],
            "scanned_at" => now()->toIso8601String(),
            "skipped" => false,
        ];
    }
}
