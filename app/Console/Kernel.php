<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\CleanExpiredFiles::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // 每小时清理一次过期文件
        $schedule->command('files:clean-expired')->hourly();
        $schedule->command('xiaoxin-file-express:queue-heartbeat')->everyMinute();
        $schedule->command('xiaoxin-file-express:ops-check --record')->everyFiveMinutes();
        $schedule->command('xiaoxin-file-express:cleanup-lan-sessions')->everyThirtyMinutes();
        $schedule->command('xiaoxin-file-express:prune-logs --days=30')->dailyAt('03:20');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
