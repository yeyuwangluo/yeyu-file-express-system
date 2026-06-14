<?php

namespace App\Console\Commands;

use App\Support\InstallationState;
use Illuminate\Console\Command;

class ResetInstallation extends Command
{
    protected $signature = 'install:reset
        {--force : 不需要确认直接执行}';

    protected $description = '重置安装状态，删除 marker 文件并清除环境变量，让系统重新进入未安装状态';

    public function handle(InstallationState $state): int
    {
        if (! $this->option('force') && ! $this->confirm('确定要重置安装状态吗？这将删除安装标记文件并清除 .env 中的 YEYU_FILE_EXPRESS_INSTALLED 变量。', false)) {
            $this->info('操作已取消。');

            return self::SUCCESS;
        }

        $markerPath = $state->markerPath();

        if (is_file($markerPath)) {
            unlink($markerPath);
            $this->info("已删除 marker 文件：{$markerPath}");
        } else {
            $this->info('marker 文件不存在，无需删除。');
        }

        $envPath = $state->envPath();

        if (is_file($envPath)) {
            $content = (string) file_get_contents($envPath);

            if (strpos($content, 'YEYU_FILE_EXPRESS_INSTALLED') !== false) {
                $content = preg_replace('/^YEYU_FILE_EXPRESS_INSTALLED=.*$/m', '', $content);
                $content = preg_replace('/\n{3,}/', "\n\n", $content);
                file_put_contents($envPath, rtrim($content) . PHP_EOL);
                $this->info('已清除 .env 中的 YEYU_FILE_EXPRESS_INSTALLED 变量。');
            } else {
                $this->info('.env 中无 YEYU_FILE_EXPRESS_INSTALLED 变量，无需处理。');
            }
        }

        $this->newLine();
        $this->info('安装状态已重置！访问 /install 即可重新进入安装向导。');

        return self::SUCCESS;
    }
}
