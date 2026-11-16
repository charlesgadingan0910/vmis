<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * P1-08 — encrypted backups of the VMIS database and uploaded documents,
 * and a restore that can be rehearsed safely.
 *
 * A backup is ONE zip file, every entry AES-256 encrypted with
 * BACKUP_PASSPHRASE:
 *   manifest.json         when, row counts and SHA-256 of every table/file
 *   db/<table>.ndjson     one JSON row per line (schema comes from migrations)
 *   files/<path>          every document on the "public" disk (OR/CR, receipts,
 *                         driver photos...)
 * Sessions, cache and queue tables are skipped — they are throw-away data.
 *
 * Backups are written to the "backups" disk (config/filesystems.php), which
 * must be object storage in production because a Laravel Cloud app's own disk
 * is wiped on every deploy.
 */
class BackupService
{
    public const CACHE_LAST_SUCCESS = 'vmis:backup:last_success';

    /** Tables that never need backing up (sessions, caches, queue). */
    protected const SKIP_TABLES = [
        'migrations', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
    ];

    protected const DIRECTORY = 'vmis';

    protected function passphrase(): string
    {
        $passphrase = (string) config('backup.passphrase');

        if (strlen($passphrase) < 12) {
            throw new RuntimeException('BACKUP_PASSPHRASE must be set to at least 12 characters before a backup can be made or restored.');
        }

        return $passphrase;
    }

    protected function disk()
    {
        return Storage::disk(config('backup.disk', 'backups'));
    }

    /**
     * Creates, uploads and prunes a backup.
     *
     * @return array{file: string, bytes: int, tables: int, rows: int, files: int, seconds: float}
     */
    public function create(bool $includeFiles = true): array
    {
        $started = microtime(true);
        $passphrase = $this->passphrase();
        $work = $this->tempDir('backup');

        try {
            $zip = new ZipArchive();
            $archive = $work . DIRECTORY_SEPARATOR . 'archive.zip';

            if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Could not create the backup archive.');
            }
            $zip->setPassword($passphrase);

            $manifest = [
                'app'        => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'connection' => config('database.default'),
                'tables'     => [],
                'files'      => [],
            ];

            $totalRows = 0;
            foreach ($this->tables() as $table) {
                $path = $work . DIRECTORY_SEPARATOR . 'db_' . $table . '.ndjson';
                $rows = $this->exportTable($table, $path);
                $totalRows += $rows;

                $zip->addFile($path, 'db/' . $table . '.ndjson');
                $this->encryptEntry($zip, 'db/' . $table . '.ndjson');
                $manifest['tables'][$table] = ['rows' => $rows, 'sha256' => hash_file('sha256', $path)];
            }

            $fileCount = 0;
            if ($includeFiles) {
                $public = Storage::disk('public');
                foreach ($public->allFiles() as $i => $relative) {
                    $stream = $public->readStream($relative);
                    if (! is_resource($stream)) {
                        continue;
                    }
                    $copy = $work . DIRECTORY_SEPARATOR . 'file_' . $i;
                    $out = fopen($copy, 'wb');
                    stream_copy_to_stream($stream, $out);
                    fclose($out);
                    fclose($stream);

                    $zip->addFile($copy, 'files/' . $relative);
                    $this->encryptEntry($zip, 'files/' . $relative);
                    $manifest['files'][$relative] = hash_file('sha256', $copy);
                    $fileCount++;
                }
            }

            $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->encryptEntry($zip, 'manifest.json');

            if (! $zip->close()) {
                throw new RuntimeException('Could not finish writing the backup archive.');
            }

            $name = self::DIRECTORY . '/vmis-backup-' . now()->format('Ymd-His') . '.zip';
            $stream = fopen($archive, 'rb');
            $this->disk()->put($name, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            $bytes = filesize($archive);
            $this->prune();
            Cache::forever(self::CACHE_LAST_SUCCESS, now()->toIso8601String());

            return [
                'file'    => $name,
                'bytes'   => $bytes,
                'tables'  => count($manifest['tables']),
                'rows'    => $totalRows,
                'files'   => $fileCount,
                'seconds' => round(microtime(true) - $started, 2),
            ];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** Backup files on the backup disk, newest first. @return array<int, string> */
    public function list(): array
    {
        $files = collect($this->disk()->files(self::DIRECTORY))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->sortDesc()
            ->values()
            ->all();

        return $files;
    }

    /** Deletes backups older than BACKUP_KEEP_DAYS (default 14), always keeping the newest. */
    public function prune(): int
    {
        $days = max(1, (int) config('backup.keep_days', 14));
        $cutoff = now()->subDays($days)->getTimestamp();
        $files = $this->list();
        $deleted = 0;

        foreach (array_slice($files, 1) as $file) {
            if ($this->disk()->lastModified($file) < $cutoff) {
                $this->disk()->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Restore REHEARSAL: unpacks a backup into a throw-away SQLite database
     * and temp folder, checks every row count and checksum against the
     * manifest, and reports how long it took. Touches nothing live.
     *
     * @return array{ok: bool, seconds: float, tables: int, rows: int, files: int, problems: array<int, string>}
     */
    public function rehearse(string $file): array
    {
        $started = microtime(true);
        $work = $this->tempDir('restore');

        try {
            [$extracted, $manifest] = $this->extract($file, $work);

            $database = $work . DIRECTORY_SEPARATOR . 'rehearsal.sqlite';
            touch($database);
            config(['database.connections.restore_rehearsal' => [
                'driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => false,
            ]]);
            DB::purge('restore_rehearsal');

            Artisan::call('migrate', ['--database' => 'restore_rehearsal', '--force' => true]);

            $problems = $this->verifyChecksums($extracted, $manifest);
            // truncate: a migration may seed default rows (e.g. account types) that the backup also holds
            $rows = $this->importTables($extracted, $manifest, 'restore_rehearsal', $problems, truncate: true);

            $files = 0;
            foreach ($manifest['files'] as $relative => $hash) {
                $path = $extracted . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $relative;
                if (! is_file($path)) {
                    $problems[] = "Missing file in backup: {$relative}";
                } elseif (hash_file('sha256', $path) !== $hash) {
                    $problems[] = "Corrupt file in backup: {$relative}";
                } else {
                    $files++;
                }
            }

            return [
                'ok'      => $problems === [],
                'seconds' => round(microtime(true) - $started, 2),
                'tables'  => count($manifest['tables']),
                'rows'    => $rows,
                'files'   => $files,
                'problems' => $problems,
            ];
        } finally {
            DB::purge('restore_rehearsal');
            File::deleteDirectory($work);
        }
    }

    /**
     * LIVE restore: REPLACES the contents of the current database with the
     * backup and copies the documents back. The schema must already exist
     * (run migrations first on a fresh database).
     *
     * @return array{seconds: float, tables: int, rows: int, files: int, problems: array<int, string>}
     */
    public function restoreLive(string $file): array
    {
        $started = microtime(true);
        $work = $this->tempDir('restore');

        try {
            [$extracted, $manifest] = $this->extract($file, $work);

            $problems = $this->verifyChecksums($extracted, $manifest);
            if ($problems !== []) {
                throw new RuntimeException('Backup failed verification, nothing was restored: ' . implode('; ', $problems));
            }

            $connection = config('database.default');
            $rows = $this->importTables($extracted, $manifest, $connection, $problems, truncate: true);

            $files = 0;
            $public = Storage::disk('public');
            foreach ($manifest['files'] as $relative => $hash) {
                $path = $extracted . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . $relative;
                if (is_file($path)) {
                    $stream = fopen($path, 'rb');
                    $public->put($relative, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    $files++;
                }
            }

            return [
                'seconds'  => round(microtime(true) - $started, 2),
                'tables'   => count($manifest['tables']),
                'rows'     => $rows,
                'files'    => $files,
                'problems' => $problems,
            ];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /* ------------------------------------------------------------------ */

    /** @return array<int, string> */
    protected function tables(): array
    {
        return collect(Schema::getTableListing())
            ->map(fn ($t) => str_contains($t, '.') ? substr($t, strrpos($t, '.') + 1) : $t)
            ->reject(fn ($t) => in_array($t, self::SKIP_TABLES, true) || str_starts_with($t, 'sqlite_'))
            ->sort()
            ->values()
            ->all();
    }

    protected function exportTable(string $table, string $path): int
    {
        $out = fopen($path, 'wb');
        $count = 0;

        $query = DB::table($table);
        if (Schema::hasColumn($table, 'id')) {
            $query->orderBy('id');
        }

        foreach ($query->cursor() as $row) {
            fwrite($out, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n");
            $count++;
        }

        fclose($out);

        return $count;
    }

    protected function encryptEntry(ZipArchive $zip, string $name): void
    {
        if (! $zip->setEncryptionName($name, ZipArchive::EM_AES_256)) {
            throw new RuntimeException("Could not encrypt backup entry {$name}.");
        }
    }

    /** @return array{0: string, 1: array<string, mixed>} extracted dir + manifest */
    protected function extract(string $file, string $work): array
    {
        $local = $work . DIRECTORY_SEPARATOR . 'incoming.zip';

        if (is_file($file)) {
            copy($file, $local);
        } else {
            if (! $this->disk()->exists($file)) {
                throw new RuntimeException("Backup not found: {$file}");
            }
            $in = $this->disk()->readStream($file);
            $out = fopen($local, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($out);
            fclose($in);
        }

        $zip = new ZipArchive();
        if ($zip->open($local) !== true) {
            throw new RuntimeException('That file is not a valid backup archive.');
        }
        $zip->setPassword($this->passphrase());

        $dir = $work . DIRECTORY_SEPARATOR . 'extracted';
        mkdir($dir, 0700, true);

        $manifestJson = $zip->getFromName('manifest.json');
        if ($manifestJson === false) {
            throw new RuntimeException('Could not read the backup. The passphrase is wrong or the file is damaged.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_contains($name, '..') || str_starts_with($name, '/')) {
                throw new RuntimeException("Unsafe path in backup: {$name}");
            }
            $target = $dir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
            File::ensureDirectoryExists(dirname($target));
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                throw new RuntimeException("Could not decrypt {$name}. The passphrase is wrong or the file is damaged.");
            }
            file_put_contents($target, $content);
        }
        $zip->close();

        $manifest = json_decode($manifestJson, true);
        $manifest['tables'] = $manifest['tables'] ?? [];
        $manifest['files'] = $manifest['files'] ?? [];

        return [$dir, $manifest];
    }

    /** @return array<int, string> */
    protected function verifyChecksums(string $dir, array $manifest): array
    {
        $problems = [];

        foreach ($manifest['tables'] as $table => $info) {
            $path = $dir . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . $table . '.ndjson';
            if (! is_file($path)) {
                $problems[] = "Missing table data: {$table}";
            } elseif (hash_file('sha256', $path) !== $info['sha256']) {
                $problems[] = "Corrupt table data: {$table}";
            }
        }

        return $problems;
    }

    /** @param array<int, string> $problems */
    protected function importTables(string $dir, array $manifest, string $connection, array &$problems, bool $truncate = false): int
    {
        $schema = Schema::connection($connection);
        $db = DB::connection($connection);
        $total = 0;

        $tables = array_values(array_filter(
            array_keys($manifest['tables']),
            function ($table) use ($schema, &$problems) {
                if ($schema->hasTable($table)) {
                    return true;
                }
                $problems[] = "Table {$table} does not exist in the target database.";

                return false;
            }
        ));
        $ordered = $this->insertionOrder($schema, $tables);

        $schema->disableForeignKeyConstraints();

        try {
            if ($truncate) {
                // children first, so rows are removed before the rows they point to
                foreach (array_reverse($ordered) as $table) {
                    $db->table($table)->delete();
                }
            }

            foreach ($ordered as $table) {
                $info = $manifest['tables'][$table];
                $path = $dir . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . $table . '.ndjson';
                $handle = fopen($path, 'rb');
                $batch = [];
                $imported = 0;

                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $batch[] = json_decode($line, true);
                    $imported++;
                    if (count($batch) >= 100) {
                        $db->table($table)->insert($batch);
                        $batch = [];
                    }
                }
                if ($batch) {
                    $db->table($table)->insert($batch);
                }
                fclose($handle);

                $actual = $db->table($table)->count();
                if ($actual !== (int) $info['rows']) {
                    $problems[] = "Row count mismatch in {$table}: backup has {$info['rows']}, restored {$actual}.";
                }
                $total += $imported;
            }
        } finally {
            $schema->enableForeignKeyConstraints();
        }

        return $total;
    }

    /**
     * Parents before children (by foreign keys), so rows can be inserted even
     * where the database enforces constraints inside a transaction.
     *
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    protected function insertionOrder($schema, array $tables): array
    {
        $deps = [];
        foreach ($tables as $table) {
            $deps[$table] = [];
            foreach ($schema->getForeignKeys($table) as $fk) {
                $parent = $fk['foreign_table'] ?? null;
                if ($parent && $parent !== $table && in_array($parent, $tables, true)) {
                    $deps[$table][$parent] = true;
                }
            }
        }

        $ordered = [];
        while ($deps) {
            $ready = array_keys(array_filter($deps, fn ($d) => array_diff_key($d, array_flip($ordered)) === []));
            if (! $ready) {
                $ready = [array_key_first($deps)]; // a foreign-key cycle: break it
            }
            foreach ($ready as $table) {
                $ordered[] = $table;
                unset($deps[$table]);
            }
        }

        return $ordered;
    }

    protected function tempDir(string $prefix): string
    {
        $dir = storage_path('app/tmp/' . $prefix . '-' . bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($dir, 0700);

        return $dir;
    }

    public function logFailure(\Throwable $e): void
    {
        Log::error('VMIS backup failed: ' . $e->getMessage(), ['exception' => get_class($e)]);
    }
}
