<?php

namespace App\Jobs;

use App\Services\FleetAlertSyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rebuilds the fleet_alerts table (registration, PMS, unserviceable and
 * driver-license alerts) on a schedule, so the Notification Center and the
 * driver app are current without anyone having to open a page first.
 *
 * The scheduler dispatches this (see routes/console.php). If it throws, the
 * queue retries it up to $tries times with a growing delay; when every
 * attempt has failed, failed() writes the reason to the application log and
 * the job lands in the failed_jobs table, from where
 * `php artisan queue:retry all` can re-run it.
 *
 * ShouldBeUnique keeps a slow run from being stacked behind another copy of
 * itself. The page-load sync in the notification controllers stays as a
 * fallback and shares the same cache key, so it backs off after a good run.
 */
class SyncFleetAlertsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Cache key the notification controllers use to rate-limit their own sync. */
    public const CACHE_LAST_SYNC = 'fleet_alerts:last_sync';

    /** Cache key holding the ISO time of the last successful run, for the status command. */
    public const CACHE_LAST_SUCCESS = 'fleet_alerts:last_success_at';

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    /**
     * Seconds to wait before each retry: 1 minute, 5 minutes, 15 minutes.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(FleetAlertSyncService $service): void
    {
        $service->sync();

        Cache::put(self::CACHE_LAST_SYNC, true, now()->addMinutes(5));
        Cache::forever(self::CACHE_LAST_SUCCESS, now()->toIso8601String());
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Fleet alert sync failed after all attempts.', [
            'job'       => static::class,
            'exception' => $exception ? get_class($exception) : null,
            'message'   => $exception?->getMessage(),
        ]);
    }
}
