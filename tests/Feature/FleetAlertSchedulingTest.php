<?php

use App\Jobs\SyncFleetAlertsJob;
use App\Services\FleetAlertSyncService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

it('schedules the fleet alert sync every thirty minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => $event->description === 'fleet-alerts-sync');

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0,30 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('schedules clean-up of old failed jobs', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => $event->description === 'queue-prune-failed');

    expect($event)->not->toBeNull();
});

it('retries a failing sync three times with a growing delay', function () {
    $job = new SyncFleetAlertsJob();

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([60, 300, 900]);
});

it('runs the alert sync and records the success time', function () {
    Cache::flush();

    $this->mock(FleetAlertSyncService::class, function ($mock) {
        $mock->shouldReceive('sync')->once();
    });

    SyncFleetAlertsJob::dispatchSync();

    expect(Cache::get(SyncFleetAlertsJob::CACHE_LAST_SUCCESS))->not->toBeNull()
        ->and(Cache::get(SyncFleetAlertsJob::CACHE_LAST_SYNC))->toBeTrue();
});

it('logs the reason when every attempt has failed', function () {
    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'Fleet alert sync failed')
            && $context['message'] === 'database is down');

    (new SyncFleetAlertsJob())->failed(new RuntimeException('database is down'));
});

it('lets the command queue the sync instead of running it', function () {
    Bus::fake();

    $this->artisan('vmis:sync-alerts', ['--queue' => true])->assertSuccessful();

    Bus::assertDispatched(SyncFleetAlertsJob::class);
});

it('lets the command run the sync right away', function () {
    $this->mock(FleetAlertSyncService::class, function ($mock) {
        $mock->shouldReceive('sync')->once();
    });

    $this->artisan('vmis:sync-alerts')->assertSuccessful();
});

it('reports the sync status', function () {
    Cache::forever(SyncFleetAlertsJob::CACHE_LAST_SUCCESS, '2026-10-21T08:00:00+00:00');

    $this->artisan('vmis:alerts-status')
        ->expectsOutputToContain('2026-10-21T08:00:00+00:00')
        ->assertSuccessful();
});
