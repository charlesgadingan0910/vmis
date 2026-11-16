<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BackupStatus extends Command
{
    protected $signature = 'vmis:backup-status';

    protected $description = 'Show when the last backup succeeded and list the backups that are stored';

    public function handle(BackupService $backups): int
    {
        $this->line('Last successful backup: ' . (Cache::get(BackupService::CACHE_LAST_SUCCESS) ?: 'never recorded'));

        $files = $backups->list();
        $this->line('Stored backups: ' . count($files));
        foreach ($files as $file) {
            $this->line('  ' . $file);
        }

        if ($files === []) {
            $this->warn('No backups found. Run "php artisan vmis:backup" and check BACKUP_PASSPHRASE and the backup disk.');
        }

        return self::SUCCESS;
    }
}
