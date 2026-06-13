<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecordQueueHeartbeat extends Command
{
    protected $signature = 'xiaoxin-file-express:queue-heartbeat';

    protected $description = 'Record queue heartbeat and queue counters.';

    public function handle(): int
    {
        $pendingJobs = Schema::hasTable('jobs') ? DB::table('jobs')->count() : null;
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : null;

        Setting::query()->updateOrCreate(['group' => 'queue', 'key' => 'worker_heartbeat_at'], ['value' => now()->toDateTimeString(), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'queue', 'key' => 'pending_jobs'], ['value' => (string) ($pendingJobs ?? 0), 'type' => 'int']);
        Setting::query()->updateOrCreate(['group' => 'queue', 'key' => 'failed_jobs'], ['value' => (string) ($failedJobs ?? 0), 'type' => 'int']);

        $this->info('Queue heartbeat recorded: pending='.($pendingJobs ?? 'n/a').', failed='.($failedJobs ?? 'n/a'));

        return self::SUCCESS;
    }
}
