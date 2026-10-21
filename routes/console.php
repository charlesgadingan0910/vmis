<?php

use App\Jobs\SyncFleetAlertsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
|
| Needs two things running on the server: the scheduler
| (`php artisan schedule:run` every minute, or `schedule:work`) and a queue
| worker (`php artisan queue:work`). See SyncFleetAlertsJob for the retry
| behaviour, and `php artisan vmis:alerts-status` to check on it.
*/

// Keep the Notification Center current without anyone opening a page.
Schedule::job(new SyncFleetAlertsJob())
    ->name('fleet-alerts-sync')
    ->everyThirtyMinutes()
    ->withoutOverlapping();

// Failed jobs are kept for 30 days for inspection, then cleared.
Schedule::command('queue:prune-failed --hours=720')
    ->name('queue-prune-failed')
    ->daily();
