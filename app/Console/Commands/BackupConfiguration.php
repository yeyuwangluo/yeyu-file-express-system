<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupConfiguration extends Command
{
    protected $signature = 'xiaoxin-file-express:backup-config';

    protected $description = 'Backup Xiaoxin File Express settings to local storage.';

    public function handle(): int
    {
        $payload = Setting::query()->orderBy('group')->orderBy('key')->get()->toArray();
        $path = 'backups/config/settings-'.now()->format('Ymd-His').'.json';

        Storage::disk('local')->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        Log::info('Configuration backup created', ['path' => $path]);
        $this->info("Created {$path}.");

        return self::SUCCESS;
    }
}
