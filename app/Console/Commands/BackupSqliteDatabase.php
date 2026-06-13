<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupSqliteDatabase extends Command
{
    protected $signature = 'xiaoxin-file-express:backup-database';

    protected $description = 'Backup the SQLite database when SQLite is used locally.';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite') {
            $this->info('Database backup is handled by the MySQL deployment strategy.');

            return self::SUCCESS;
        }

        $database = DB::connection('sqlite')->getDatabaseName();
        if (! is_string($database) || ! is_file($database)) {
            $this->warn('SQLite database file not found.');

            return self::FAILURE;
        }

        $path = 'backups/database/database-'.now()->format('Ymd-His').'.sqlite';
        Storage::disk('local')->put($path, file_get_contents($database));
        Log::info('SQLite database backup created', ['path' => $path]);
        $this->info("Created {$path}.");

        return self::SUCCESS;
    }
}
