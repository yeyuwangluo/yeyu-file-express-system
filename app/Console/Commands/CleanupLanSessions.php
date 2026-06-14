<?php

namespace App\Console\Commands;

use App\Models\LanSession;
use App\Models\LanSignal;
use App\Models\Setting;
use App\Support\YeyuFileExpressSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupLanSessions extends Command
{
    protected $signature = 'yeyu-file-express:cleanup-lan-sessions';

    protected $description = 'Mark expired LAN transfer sessions as expired.';

    public function handle(): int
    {
        $expired = LanSession::query()
            ->whereNotIn('status', ['completed', 'cancelled', 'expired'])
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'updated_at' => now()]);

        $config = YeyuFileExpressSettings::lanTransfer();
        $completedBefore = now()->subMinutes((int) $config['completedRetentionMinutes']);
        $textBefore = now()->subMinutes((int) $config['textRetentionMinutes']);

        $completedIds = LanSession::query()
            ->whereIn('status', ['completed', 'cancelled', 'expired'])
            ->where('transfer_kind', '!=', 'text')
            ->where('updated_at', '<=', $completedBefore)
            ->pluck('id');
        $completedSignalsDeleted = $completedIds->isNotEmpty()
            ? LanSignal::query()->whereIn('lan_session_id', $completedIds)->delete()
            : 0;
        $completedDeleted = $completedIds->isNotEmpty()
            ? LanSession::query()->whereIn('id', $completedIds)->delete()
            : 0;

        $textIds = LanSession::query()
            ->where('transfer_kind', 'text')
            ->whereIn('status', ['completed', 'cancelled', 'expired'])
            ->where('updated_at', '<=', $textBefore)
            ->pluck('id');
        $textSignalsDeleted = $textIds->isNotEmpty()
            ? LanSignal::query()->whereIn('lan_session_id', $textIds)->delete()
            : 0;
        $textDeleted = $textIds->isNotEmpty()
            ? LanSession::query()->whereIn('id', $textIds)->delete()
            : 0;

        Log::info('LAN session cleanup finished', compact('expired', 'completedDeleted', 'textDeleted', 'completedSignalsDeleted', 'textSignalsDeleted'));
        $summary = "Expired {$expired} LAN sessions, deleted {$completedDeleted} completed sessions, {$textDeleted} old text sessions, and ".($completedSignalsDeleted + $textSignalsDeleted)." signals.";
        Setting::query()->updateOrCreate(['group' => 'maintenance', 'key' => 'lan_cleanup_last_at'], ['value' => now()->toDateTimeString(), 'type' => 'string']);
        Setting::query()->updateOrCreate(['group' => 'maintenance', 'key' => 'lan_cleanup_last_summary'], ['value' => $summary, 'type' => 'string']);
        $this->info($summary);

        return self::SUCCESS;
    }
}
