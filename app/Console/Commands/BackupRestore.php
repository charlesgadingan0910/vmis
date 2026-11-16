<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupRestore extends Command
{
    protected $signature = 'vmis:backup-restore
        {file? : Backup name on the backup disk, or a path to a .zip (default: the newest)}
        {--live : REPLACE the live database and documents with the backup (default is a safe rehearsal)}
        {--force : Skip the confirmation prompt for --live}';

    protected $description = 'Rehearse a restore into a throw-away database (default), or restore the live system with --live';

    public function handle(BackupService $backups): int
    {
        $file = $this->argument('file') ?: ($backups->list()[0] ?? null);

        if (! $file) {
            $this->error('No backup found to restore.');

            return self::FAILURE;
        }

        $this->line("Backup: {$file}");

        try {
            if ($this->option('live')) {
                return $this->live($backups, $file);
            }

            $result = $backups->rehearse($file);
        } catch (\Throwable $e) {
            $this->error('Restore failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Rehearsal: %d tables, %d rows, %d documents checked in %s seconds.',
            $result['tables'], $result['rows'], $result['files'], $result['seconds']
        ));

        if (! $result['ok']) {
            foreach ($result['problems'] as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        $this->info('Rehearsal passed: every table and document matches the backup. Nothing live was touched.');

        return self::SUCCESS;
    }

    protected function live(BackupService $backups, string $file): int
    {
        if (! $this->option('force') && ! $this->confirm('This REPLACES the current database and documents with the backup. Continue?', false)) {
            $this->warn('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        $result = $backups->restoreLive($file);

        $this->line(sprintf('Restored %d tables, %d rows and %d documents in %s seconds.', $result['tables'], $result['rows'], $result['files'], $result['seconds']));

        foreach ($result['problems'] as $problem) {
            $this->error($problem);
        }

        return $result['problems'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
