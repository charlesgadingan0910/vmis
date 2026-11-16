<?php

use App\Models\Station;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('backups');
    Storage::fake('public');
    config(['backup.passphrase' => 'correct horse battery staple', 'backup.disk' => 'backups']);
});

function backupSeed(): array
{
    $unit = Unit::create(['unit_name' => 'Alpha Unit', 'unit_abbvr' => 'ALPHA']);
    $station = Station::create(['unit_id' => $unit->id, 'station_name' => 'Alpha Station', 'station_abbvr' => 'ALS']);
    $user = User::create([
        'account_type' => 'ADMINISTRATOR', 'lastname' => 'Backup', 'firstname' => 'Test', 'fullname' => 'Backup Test',
        'badge_number' => 'BKP-1', 'email' => 'backup@example.test', 'password' => 'secret-password',
        'is_active' => '1', 'is_password_changed' => '1', 'unit_id' => $unit->id, 'station_id' => $station->id,
    ]);
    Storage::disk('public')->put('vehicle_docs/or/sample.pdf', 'pretend this is an OR document');

    return compact('unit', 'station', 'user');
}

function backupFile(): string
{
    return Storage::disk('backups')->files('vmis')[0];
}

it('refuses to back up without a passphrase', function () {
    config(['backup.passphrase' => null]);

    $this->artisan('vmis:backup')->assertFailed();

    expect(Storage::disk('backups')->files('vmis'))->toBe([]);
});

it('writes an encrypted backup of the database and documents', function () {
    backupSeed();

    $this->artisan('vmis:backup')->assertSuccessful();

    $zip = new ZipArchive();
    $zip->open(Storage::disk('backups')->path(backupFile()));

    // unreadable without the passphrase
    expect($zip->getFromName('manifest.json'))->toBeFalse();

    $zip->setPassword('correct horse battery staple');
    $manifest = json_decode($zip->getFromName('manifest.json'), true);

    expect($manifest['tables']['users']['rows'])->toBe(1)
        ->and($manifest['tables']['units']['rows'])->toBe(1)
        ->and(array_keys($manifest['files']))->toContain('vehicle_docs/or/sample.pdf')
        ->and($manifest['tables'])->not->toHaveKey('sessions');
});

it('passes a restore rehearsal without touching live data', function () {
    backupSeed();
    $this->artisan('vmis:backup')->assertSuccessful();

    $this->artisan('vmis:backup-restore')
        ->expectsOutputToContain('Rehearsal passed')
        ->assertSuccessful();

    expect(User::count())->toBe(1);
});

it('cannot be opened with the wrong passphrase', function () {
    backupSeed();
    $this->artisan('vmis:backup')->assertSuccessful();

    config(['backup.passphrase' => 'a completely different secret']);

    $this->artisan('vmis:backup-restore')->assertFailed();
});

it('restores the live database and documents from a backup', function () {
    $seed = backupSeed();
    $this->artisan('vmis:backup')->assertSuccessful();

    // disaster: everything is gone
    DB::table('users')->delete();
    DB::table('stations')->delete();
    DB::table('units')->delete();
    Storage::disk('public')->delete('vehicle_docs/or/sample.pdf');

    $this->artisan('vmis:backup-restore', ['--live' => true, '--force' => true])->assertSuccessful();

    expect(User::where('badge_number', 'BKP-1')->exists())->toBeTrue()
        ->and(Unit::count())->toBe(1)
        ->and(Station::first()->unit_id)->toBe($seed['unit']->id)
        ->and(Storage::disk('public')->get('vehicle_docs/or/sample.pdf'))->toBe('pretend this is an OR document');
});

it('keeps only the backups inside the retention window, but never the last one', function () {
    config(['backup.keep_days' => 7]);
    backupSeed();

    $disk = Storage::disk('backups');
    $disk->put('vmis/vmis-backup-20200101-020000.zip', 'old');
    touch($disk->path('vmis/vmis-backup-20200101-020000.zip'), now()->subDays(30)->getTimestamp());

    $this->artisan('vmis:backup')->assertSuccessful();

    expect($disk->files('vmis'))->toHaveCount(1)
        ->and($disk->exists('vmis/vmis-backup-20200101-020000.zip'))->toBeFalse();
});

it('schedules the nightly backup', function () {
    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => $e->description === 'vmis-backup');

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 2 * * *');
});
