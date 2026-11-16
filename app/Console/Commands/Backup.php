<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class Backup extends Command
{
    protected $signature = 'vmis:backup {--no-files : Back up the database only, skip uploaded documents}';

    protected $description = 'Create an encrypted backup of the database and uploaded documents and store it on the backup disk';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->create(! $this->option('no-files'));
        } catch (\Throwable $e) {
            $backups->logFailure($e);
            $this->error('Backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup saved: {$result['file']}");
        $this->line(sprintf(
            '%d tables, %d rows, %d documents, %s, %s seconds.',
            $result['tables'], $result['rows'], $result['files'], $this->size($result['bytes']), $result['seconds']
        ));

        return self::SUCCESS;
    }

    protected function size(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024, 1) . ' KB';
    }
}
