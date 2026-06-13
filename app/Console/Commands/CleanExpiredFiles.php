<?php

namespace App\Console\Commands;

use App\Models\SharedFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanExpiredFiles extends Command
{
    protected $signature = 'files:clean-expired';
    protected $description = 'Clean up expired files';

    public function handle()
    {
        $this->info('开始清理过期文件...');

        // 查找所有过期的文件（expires_at < 当前时间 且 status != deleted）
        $expiredFiles = SharedFile::where('expires_at', '<', now())
            ->where('status', '!=', 'deleted')
            ->get();

        $deletedCount = 0;
        $failedCount = 0;
        $totalSize = 0;

        foreach ($expiredFiles as $file) {
            try {
                // 删除物理文件
                $disk = Storage::disk($file->disk);
                $filePath = $file->path . '/' . $file->stored_name;
                
                if ($disk->exists($filePath)) {
                    $fileSize = $disk->size($filePath);
                    $disk->delete($filePath);
                    $totalSize += $fileSize;
                    $this->line("已删除物理文件: {$file->disk}://{$filePath} ({$fileSize} bytes)");
                } else {
                    $this->warn("物理文件不存在: {$file->disk}://{$filePath}");
                }

                // 标记为已删除
                $file->status = 'deleted';
                $file->deleted_at = now();
                $file->save();

                $deletedCount++;
                $this->line("已标记文件为已删除: ID {$file->id}, Code {$file->code}");

            } catch (\Exception $e) {
                $failedCount++;
                $this->error("删除文件失败: ID {$file->id}, Code {$file->code}, Error: {$e->getMessage()}");
                Log::error('Failed to delete expired file', [
                    'file_id' => $file->id,
                    'code' => $file->code,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info('');
        $this->info('清理完成:');
        $this->info("  成功删除: {$deletedCount} 个文件");
        $this->info("  失败: {$failedCount} 个文件");
        $this->info("  释放空间: " . $this->formatBytes($totalSize));
        
        Log::info('Expired files cleaned up', [
            'deleted_count' => $deletedCount,
            'failed_count' => $failedCount,
            'total_size' => $totalSize,
        ]);

        return 0;
    }

    private function formatBytes($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
