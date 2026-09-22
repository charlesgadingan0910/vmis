<?php

namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Turns a vehicle's flat "next PMS date" into an explainable urgency score
 * instead of treating every overdue/soon vehicle the same. The formula only
 * uses data already on file — the recorded due date, past service
 * intervals, and odometer pace from maintenance history — no external call,
 * no black-box model. A reviewer can always see exactly why a vehicle
 * ranked where it did (see the "reason" string), which matters more for a
 * government fleet audit trail than squeezing out extra predictive accuracy.
 */
class PmsPredictionService
{
    /**
     * Same threshold VehicleController/MaintenanceController already use for
     * "due soon", kept here too so scoring and the existing badges agree.
     */
    public const DUE_SOON_DAYS = 14;

    /**
     * @return array{urgency: int, priority: string, reason: string, days: int}|null
     *         Null when the vehicle has no next_pms_date at all — there's
     *         nothing to score yet.
     */
    public function scoreVehicle(Vehicle $vehicle): ?array
    {
        if (! $vehicle->next_pms_date) {
            return null;
        }

        $days = (int) now()->startOfDay()->diffInDays($vehicle->next_pms_date->copy()->startOfDay(), false);

        $records = $vehicle->maintenanceRecords()
            ->whereNotNull('service_date')
            ->limit(6)
            ->get();

        [$avgKmPerDay, $paceReason] = $this->usagePace($records);
        [$lateCount, $totalCompared] = $this->lateServiceHistory($records);

        $score = $this->dateScore($days);
        $reasons = [$this->dateReason($days)];

        if ($avgKmPerDay !== null) {
            // Above ~40km/day is a rough "actively used" pace for a typical
            // support/patrol vehicle. High mileage between services means the
            // calendar-based due date is likely already optimistic, so it adds
            // urgency rather than waiting for the date alone to catch up.
            if ($avgKmPerDay >= 60) {
                $score += 20;
            } elseif ($avgKmPerDay >= 40) {
                $score += 10;
            }
            $reasons[] = $paceReason;
        }

        if ($totalCompared > 0 && $lateCount > 0) {
            $score += min(15, $lateCount * 5);
            $reasons[] = "serviced late {$lateCount} of last {$totalCompared} time(s)";
        }

        $score = max(0, min(100, $score));

        return [
            'urgency' => $score,
            'priority' => $this->priorityLabel($score),
            'reason' => implode(' · ', $reasons),
            'days' => $days,
        ];
    }

    /**
     * Bounded ranked list of the most urgent vehicles in scope — deliberately
     * limited up front to vehicles already due soon/overdue (and not BER,
     * since those are already written off), so scoring only ever runs over a
     * small, pre-filtered slice rather than the whole fleet on every request.
     *
     * @param  Builder<Vehicle>|null  $scopedQuery  A Vehicle query with the caller's own
     *         unit/station visibility scoping already applied; a fresh Vehicle::query()
     *         is used when omitted.
     * @return Collection<int, array{vehicle: Vehicle, urgency: int, priority: string, reason: string, days: int}>
     */
    public function topPriorityVehicles(?Builder $scopedQuery = null, int $limit = 5): Collection
    {
        $query = $scopedQuery ?? Vehicle::query();

        $cutoff = now()->addDays(self::DUE_SOON_DAYS)->endOfDay();

        $candidates = (clone $query)
            ->whereNotNull('next_pms_date')
            ->where('next_pms_date', '<=', $cutoff)
            ->where('status', '!=', 'BER')
            ->orderBy('next_pms_date')
            ->limit(50) // a generous ceiling on how many get individually scored per request
            ->get();

        return $candidates
            ->map(function (Vehicle $vehicle) {
                $score = $this->scoreVehicle($vehicle);

                return $score ? array_merge(['vehicle' => $vehicle], $score) : null;
            })
            ->filter()
            ->sortByDesc('urgency')
            ->take($limit)
            ->values();
    }

    protected function dateScore(int $days): int
    {
        if ($days < 0) {
            // Overdue: climbs quickly with how many days overdue, capping out
            // around a month so a merely-old overdue vehicle doesn't drown out
            // one whose usage pace or history make it genuinely more urgent.
            return (int) min(85, 55 + (abs($days) * 1.2));
        }
        if ($days <= self::DUE_SOON_DAYS) {
            // Due soon: the closer to the date, the higher — but capped below
            // "overdue" territory so a genuinely overdue vehicle never ranks
            // beneath one that's merely coming up.
            return (int) round(50 - (($days / self::DUE_SOON_DAYS) * 35));
        }

        return 5;
    }

    protected function dateReason(int $days): string
    {
        if ($days < 0) {
            return 'overdue '.abs($days).' day(s)';
        }
        if ($days === 0) {
            return 'due today';
        }

        return 'due in '.$days.' day(s)';
    }

    /**
     * Average km/day pace derived from the odometer readings on the most
     * recent maintenance records that have one.
     *
     * @param  Collection  $records  newest first (as maintenanceRecords() already orders)
     * @return array{0: ?float, 1: string}
     */
    protected function usagePace(Collection $records): array
    {
        $withOdo = $records->filter(fn ($r) => $r->odometer_km !== null)->values();
        if ($withOdo->count() < 2) {
            return [null, ''];
        }

        $newest = $withOdo->first();
        $oldest = $withOdo->last();

        $kmDelta = $newest->odometer_km - $oldest->odometer_km;
        $daysDelta = $oldest->service_date->diffInDays($newest->service_date);

        if ($kmDelta <= 0 || $daysDelta <= 0) {
            return [null, ''];
        }

        $avgKmPerDay = round($kmDelta / $daysDelta, 1);
        $descriptor = $avgKmPerDay >= 60 ? 'heavily used' : ($avgKmPerDay >= 40 ? 'actively used' : 'lightly used');

        return [$avgKmPerDay, "averaging {$avgKmPerDay} km/day ({$descriptor})"];
    }

    /**
     * How many of the last few services happened after their OWN previously
     * recorded due date — a documented pattern of slipping past schedule,
     * independent of how the current one turns out.
     *
     * @param  Collection  $records  newest first
     * @return array{0: int, 1: int} [lateCount, totalCompared]
     */
    protected function lateServiceHistory(Collection $records): array
    {
        $ordered = $records->sortBy('service_date')->values();
        $lateCount = 0;
        $totalCompared = 0;

        for ($i = 1; $i < $ordered->count(); $i++) {
            $previous = $ordered[$i - 1];
            $current = $ordered[$i];

            if (! $previous->next_due_date) {
                continue;
            }

            $totalCompared++;
            if ($current->service_date->gt($previous->next_due_date)) {
                $lateCount++;
            }
        }

        return [$lateCount, $totalCompared];
    }

    protected function priorityLabel(int $score): string
    {
        return match (true) {
            $score >= 75 => 'critical',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };
    }
}
