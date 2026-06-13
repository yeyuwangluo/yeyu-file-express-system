<?php

namespace App\Filesystem;

use Illuminate\Contracts\Filesystem\Filesystem as FilesystemContract;
use App\Services\Netdisk123Service;

class Netdisk123FilesystemAdapter implements FilesystemContract
{
    protected Netdisk123Service $service;
    protected string $baseUrl = '';

    public function __construct(Netdisk123Service $service, string $baseUrl = '')
    {
        $this->service = $service;
        $this->baseUrl = $baseUrl;
    }

    public function exists($path): bool
    {
        return true;
    }

    public function get($path): string
    {
        throw new \Exception('Direct read from 123 netdisk not supported');
    }

    public function readStream($path)
    {
        throw new \Exception('Direct read from 123 netdisk not supported');
    }

    public function put($path, $contents, $options = []): bool
    {
        $tempFile = is_resource($contents) ? $this->streamToTemp($contents) : $this->createTempFile($contents);
        
        try {
            $result = $this->service->uploadFile($tempFile, basename($path));
            unlink($tempFile);
            
            return isset($result['success']) && $result['success'] === true;
        } catch (\Exception $e) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw $e;
        }
    }

    public function writeStream($path, $resource, $options = []): bool
    {
        return $this->put($path, $resource, $options);
    }

    public function delete($path): bool
    {
        return true;
    }

    public function deleteDirectory($directory): bool
    {
        return true;
    }

    public function createDirectory($path, $config = []): bool
    {
        return true;
    }

    public function makeDirectory($path, $mode = 0755, $recursive = false): bool
    {
        return true;
    }

    public function files($directory = null, $recursive = false): array
    {
        return [];
    }

    public function allFiles($directory = null): array
    {
        return [];
    }

    public function directories($directory = null, $recursive = false): array
    {
        return [];
    }

    public function allDirectories($directory = null): array
    {
        return [];
    }

    public function copy($from, $to): bool
    {
        return false;
    }

    public function move($from, $to): bool
    {
        return false;
    }

    public function size($path): int
    {
        return 0;
    }

    public function lastModified($path): int
    {
        return time();
    }

    public function mimeType($path): string
    {
        return 'application/octet-stream';
    }

    public function url($path): string
    {
        return $this->baseUrl;
    }

    public function getVisibility($path)
    {
        return 'private';
    }

    public function setVisibility($path, $visibility): bool
    {
        return true;
    }

    public function prepend($path, $data): bool
    {
        return false;
    }

    public function append($path, $data): bool
    {
        return false;
    }

    protected function createTempFile(string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'netdisk123_');
        file_put_contents($tempFile, $content);
        return $tempFile;
    }

    protected function streamToTemp($resource): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'netdisk123_');
        $dest = fopen($tempFile, 'w');
        stream_copy_to_stream($resource, $dest);
        fclose($dest);
        return $tempFile;
    }
}
