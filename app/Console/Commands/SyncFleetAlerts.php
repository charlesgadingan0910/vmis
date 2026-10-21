<?php

namespace App\Console\Commands;

use App\Jobs\SyncFleetAlertsJob;
use App\Services\FleetAlertSyncService;
use Illuminate\Console\Command;

class SyncFleetAlerts extends Command
{
    protected $signature = 'vmis:sync-alerts {--queue : Dispatch to the queue instead of running now}';

    protected $description = 'Rebuild fleet alerts (registration, PMS, unserviceable, driver license) now';

    public function handle(FleetAlertSyncService $service): int
    {
        if ($this->option('queue')) {
            SyncFleetAlertsJob::dispatch();
            $this->info('Fleet alert sync queued. A queue worker must be running to process it.');

            return self::SUCCESS;
        }

        (new SyncFleetAlertsJob())->handle($service);

        $this->info('Fleet alerts synced.');

        return self::SUCCESS;
    }
}
