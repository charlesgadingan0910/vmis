<?php

namespace App\Console\Commands;

use App\Jobs\SyncFleetAlertsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FleetAlertsStatus extends Command
{
    protected $signature = 'vmis:alerts-status';

    protected $description = 'Show when fleet alerts last synced and whether any sync jobs are waiting or failed';

    public function handle(): int
    {
        $lastSuccess = Cache::get(SyncFleetAlertsJob::CACHE_LAST_SUCCESS);

        $this->line('Last successful sync: ' . ($lastSuccess ?: 'never recorded'));

        if (Schema::hasTable('jobs')) {
            $this->line('Waiting in queue:     ' . DB::table('jobs')->where('payload', 'like', '%SyncFleetAlertsJob%')->count());
        }

        if (Schema::hasTable('failed_jobs')) {
            $failed = DB::table('failed_jobs')->where('payload', 'like', '%SyncFleetAlertsJob%')->count();
            $this->line('Failed sync jobs:      ' . $failed);

            if ($failed > 0) {
                $this->warn('Run "php artisan queue:retry all" after fixing the cause, and check storage/logs/laravel.log.');
            }
        }

        return self::SUCCESS;
    }
}
