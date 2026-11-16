<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backups (P1-08)
    |--------------------------------------------------------------------------
    |
    | passphrase  AES-256 key for every backup file. Required; at least 12
    |             characters. Keep a copy OFF the server — lose it and the
    |             backups cannot be opened.
    | disk        Filesystem disk (config/filesystems.php) holding the backups.
    | keep_days   Backups older than this are deleted; the newest is always kept.
    |
    */

    'passphrase' => env('BACKUP_PASSPHRASE'),

    'disk' => env('BACKUP_DISK', 'backups'),

    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),

];
