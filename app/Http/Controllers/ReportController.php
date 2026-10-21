<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FuelLog;
use App\Models\MaintenanceRecord;
use App\Models\Station;
use App\Models\TripLog;
use App\Models\Unit;
use App\Models\Vehicle;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports: read-only, filterable summaries of the data VMIS already holds,
 * delivered two ways from the same query — a print-friendly page (browser
 * "Print / Save as PDF") and a CSV download that opens directly in Excel.
 *
 * Neither output needs an extra Composer package; if native .xlsx or
 * server-rendered PDF files are wanted later, only renderCsv()/the print
 * view need to change — every report builder below already returns plain
 * columns + rows.
 *
 * Who can run reports: every account type except DRIVER (drivers use the
 * mobile app). Visibility matches Vehicle Inventory exactly: SUPER
 * ADMINISTRATOR, ADMINISTRATOR and VIEWER see the whole fleet, UNIT
 * ADMINISTRATOR only their own unit, STATION ADMINISTRATOR only their own
 * station. The scope is applied inside vehicleQuery(), which every report
 * is built on, so no report can return a vehicle the user couldn't open in
 * Vehicle Inventory.
 */
class ReportController extends Controller
{
    protected const ROLE_SUPER_ADMIN   = 'SUPER ADMINISTRATOR';
    protected const ROLE_ADMIN         = 'ADMINISTRATOR';
    protected const ROLE_UNIT_ADMIN    = 'UNIT ADMINISTRATOR';
    protected const ROLE_STATION_ADMIN = 'STATION ADMINISTRATOR';
    protected const ROLE_VIEWER        = 'VIEWER';

    protected const ALLOWED_ROLES = [
        self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN, self::ROLE_UNIT_ADMIN, self::ROLE_STATION_ADMIN, self::ROLE_VIEWER,
    ];

    /**
     * Report catalog: slug => card details. `dates` says whether the date
     * range filter applies to that report (the Master Vehicle List is a
     * snapshot of today, so it ignores dates).
     *
     * @var array<string, array{title: string, description: string, icon: string, dates: bool}>
     */
    public const REPORTS = [
        'master-vehicles' => [
            'title'       => 'Master Vehicle List',
            'description' => 'Every vehicle with unit, station, status, assigned driver, odometer, registration expiry and next PMS.',
            'icon'        => 'fa-car-side',
            'dates'       => false,
        ],
        'maintenance-history' => [
            'title'       => 'Repair & Maintenance History',
            'description' => 'All maintenance and repair jobs with stage, performing party, odometer and cost.',
            'icon'        => 'fa-tools',
            'dates'       => true,
        ],
        'unit-recap' => [
            'title'       => 'Unit Recap Summary',
            'description' => 'Per unit: fleet size by status, registrations due, and repair and fuel spending.',
            'icon'        => 'fa-sitemap',
            'dates'       => true,
        ],
        'fuel-summary' => [
            'title'       => 'Fuel Summary',
            'description' => 'Per vehicle: refuels, liters, cost, average price and fuel efficiency (km/L).',
            'icon'        => 'fa-gas-pump',
            'dates'       => true,
        ],
        'trip-summary' => [
            'title'       => 'Trip Summary',
            'description' => 'Per vehicle: number of trips, distance covered and top speed.',
            'icon'        => 'fa-route',
            'dates'       => true,
        ],
    ];

    protected const STATUS_LABELS = [
        'SERVICEABLE'   => 'Serviceable',
        'UNSERVICEABLE' => 'Unserviceable',
        'BER'           => 'BER',
    ];

    /**
     * @return array{0: \App\Models\User, 1: string}
     */
    protected function authorizeAccess(): array
    {
        $user = auth()->user();
        $role = strtoupper(trim((string) $user->account_type));

        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            abort(403, 'Your account type does not have access to Reports.');
        }

        return [$user, $role];
    }

    public function index()
    {
        [$user, $role] = $this->authorizeAccess();

        $unitsQuery = Unit::query()->orderBy('unit_name');
        $stationsQuery = Station::query()->orderBy('station_name');

        if ($role === self::ROLE_UNIT_ADMIN) {
            $unitsQuery->where('id', $user->unit_id);
            $stationsQuery->where('unit_id', $user->unit_id);
        } elseif ($role === self::ROLE_STATION_ADMIN) {
            $stationsQuery->where('id', $user->station_id);
            $unitsQuery->where('id', optional(Station::find($user->station_id))->unit_id);
        }

        return view('reports.index', [
            'reports'          => self::REPORTS,
            'units'            => $unitsQuery->get(),
            'stations'         => $stationsQuery->get(),
            'statusLabels'     => self::STATUS_LABELS,
            'maintenanceTypes' => MaintenanceRecord::TYPES,
        ]);
    }

    public function show(Request $request, string $report)
    {
        [$user, $role] = $this->authorizeAccess();

        if (! array_key_exists($report, self::REPORTS)) {
            abort(404);
        }

        $filters = $this->validatedFilters($request);

        $data = match ($report) {
            'master-vehicles'     => $this->masterVehicles($filters, $user, $role),
            'maintenance-history' => $this->maintenanceHistory($filters, $user, $role),
            'unit-recap'          => $this->unitRecap($filters, $user, $role),
            'fuel-summary'        => $this->fuelSummary($filters, $user, $role),
            'trip-summary'        => $this->tripSummary($filters, $user, $role),
        };

        $meta = [
            'title'        => self::REPORTS[$report]['title'],
            'generated_at' => now(),
            'generated_by' => $user->fullname ?: trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? '')),
            'scope'        => $this->describeFilters($filters, self::REPORTS[$report]['dates']),
        ];

        $format = $filters['format'] ?? 'html';

        ActivityLog::record(
            'exported',
            'Reports',
            'Generated the "' . $meta['title'] . '" report (' . ($format === 'csv' ? 'Excel/CSV download' : 'print view') . ', ' . count($data['rows']) . ' rows).',
            null,
            ['after' => array_filter($filters, fn ($value) => $value !== null && $value !== '')]
        );

        if ($format === 'csv') {
            return $this->renderCsv($report, $data, $meta);
        }

        return view('reports.print', [
            'meta'    => $meta,
            'columns' => $data['columns'],
            'rows'    => array_map(
                fn (array $row) => $this->formatRow($row, $data['columns'], false),
                $data['rows']
            ),
            'totals'  => $data['totals'] ? $this->formatRow($data['totals'], $data['columns'], false) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedFilters(Request $request): array
    {
        $validator = Validator::make($request->query(), [
            'unit_id'          => ['nullable', 'integer'],
            'station_id'       => ['nullable', 'integer'],
            'status'           => ['nullable', 'in:' . implode(',', array_keys(self::STATUS_LABELS))],
            'maintenance_type' => ['nullable', 'in:' . implode(',', array_keys(MaintenanceRecord::TYPES))],
            'date_from'        => ['nullable', 'date'],
            'date_to'          => ['nullable', 'date', 'after_or_equal:date_from'],
            'format'           => ['nullable', 'in:html,csv'],
        ]);

        if ($validator->fails()) {
            abort(422, $validator->errors()->first());
        }

        return $validator->validated();
    }

    /**
     * The base of every report: the vehicles this user may see, narrowed by
     * the unit/station/status filters. Role scope comes first and the
     * filters can only narrow it further, never widen it.
     */
    protected function vehicleQuery(array $filters, $user, string $role): Builder
    {
        $query = Vehicle::query();

        if ($role === self::ROLE_UNIT_ADMIN) {
            $query->where('unit_id', $user->unit_id);
        } elseif ($role === self::ROLE_STATION_ADMIN) {
            $query->where('station_id', $user->station_id);
        }

        if (! empty($filters['unit_id'])) {
            $query->where('unit_id', $filters['unit_id']);
        }
        if (! empty($filters['station_id'])) {
            $query->where('station_id', $filters['station_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Applies the date range to a date column of the given query. Used by
     * every report that honors dates. For maintenance records the column is
     * service_date, which holds the request date until the job is completed.
     */
    protected function applyDates($query, array $filters, string $column): void
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    // ------------------------------------------------------------------
    // Report builders — each returns ['columns', 'rows', 'totals'].
    // Column types: text, int, money, dec1, dec2.
    // ------------------------------------------------------------------

    protected function masterVehicles(array $filters, $user, string $role): array
    {
        $vehicles = $this->vehicleQuery($filters, $user, $role)
            ->with(['type', 'unit', 'station', 'driver', 'driver2', 'latestRegistration'])
            ->orderBy('plate_number')
            ->get();

        $rows = [];
        foreach ($vehicles as $vehicle) {
            $rows[] = [
                strtoupper($vehicle->plate_number),
                trim($vehicle->make . ' ' . $vehicle->model),
                optional($vehicle->type)->name,
                $vehicle->year_model,
                $this->statusLabel($vehicle),
                $this->unitLabel($vehicle),
                optional($vehicle->station)->station_name,
                $this->personName($vehicle->driver),
                (int) $vehicle->odometer_km,
                optional(optional($vehicle->latestRegistration)->expiry_date)->format('Y-m-d'),
                optional($vehicle->next_pms_date)->format('Y-m-d'),
            ];
        }

        return [
            'columns' => [
                ['label' => 'Plate No.', 'type' => 'text'],
                ['label' => 'Make / Model', 'type' => 'text'],
                ['label' => 'Type', 'type' => 'text'],
                ['label' => 'Year', 'type' => 'text'],
                ['label' => 'Status', 'type' => 'text'],
                ['label' => 'Unit', 'type' => 'text'],
                ['label' => 'Station', 'type' => 'text'],
                ['label' => 'Assigned Driver', 'type' => 'text'],
                ['label' => 'Odometer (km)', 'type' => 'int'],
                ['label' => 'Registration Expiry', 'type' => 'text'],
                ['label' => 'Next PMS', 'type' => 'text'],
            ],
            'rows'   => $rows,
            'totals' => null,
        ];
    }

    protected function maintenanceHistory(array $filters, $user, string $role): array
    {
        $vehicleIds = $this->vehicleQuery($filters, $user, $role)->pluck('id');

        $query = MaintenanceRecord::query()
            ->with(['vehicle.unit'])
            ->whereIn('vehicle_id', $vehicleIds)
            ->orderByDesc('service_date')
            ->orderByDesc('id');

        if (! empty($filters['maintenance_type'])) {
            $query->where('maintenance_type', $filters['maintenance_type']);
        }
        $this->applyDates($query, $filters, 'service_date');

        $rows = [];
        $totalCost = 0.0;
        foreach ($query->get() as $record) {
            $date = $record->service_date;
            $cost = $record->cost !== null ? (float) $record->cost : null;
            $totalCost += (float) $cost;

            $rows[] = [
                $record->control_number,
                $date ? $date->format('Y-m-d') : null,
                optional($record->vehicle)->plate_number ? strtoupper($record->vehicle->plate_number) : null,
                $record->vehicle ? $this->unitLabel($record->vehicle) : null,
                MaintenanceRecord::TYPES[$record->maintenance_type] ?? $record->maintenance_type,
                MaintenanceRecord::STAGES[$record->stage] ?? $record->stage,
                $record->description,
                $record->performed_by,
                $record->odometer_km,
                $cost,
            ];
        }

        $columns = [
            ['label' => 'Control No.', 'type' => 'text'],
            ['label' => 'Date', 'type' => 'text'],
            ['label' => 'Plate No.', 'type' => 'text'],
            ['label' => 'Unit', 'type' => 'text'],
            ['label' => 'Type', 'type' => 'text'],
            ['label' => 'Stage', 'type' => 'text'],
            ['label' => 'Description', 'type' => 'text'],
            ['label' => 'Performed By', 'type' => 'text'],
            ['label' => 'Odometer (km)', 'type' => 'int'],
            ['label' => 'Cost (PHP)', 'type' => 'money'],
        ];

        return [
            'columns' => $columns,
            'rows'    => $rows,
            'totals'  => $rows ? array_merge(['TOTAL'], array_fill(0, 8, null), [$totalCost]) : null,
        ];
    }

    protected function unitRecap(array $filters, $user, string $role): array
    {
        $vehicles = $this->vehicleQuery($filters, $user, $role)
            ->with(['unit', 'latestRegistration'])
            ->get();

        $vehiclesByUnit = $vehicles->groupBy(fn (Vehicle $vehicle) => $vehicle->unit_id ?? 0);
        $unitByVehicle = $vehicles->pluck('unit_id', 'id');

        $jobs = MaintenanceRecord::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->tap(fn ($q) => $this->applyDates($q, $filters, 'service_date'))
            ->selectRaw('vehicle_id, COUNT(*) AS job_count, SUM(cost) AS job_cost')
            ->groupBy('vehicle_id')
            ->get();

        $fuel = FuelLog::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->tap(fn ($q) => $this->applyDates($q, $filters, 'refuel_date'))
            ->selectRaw('vehicle_id, SUM(total_cost) AS fuel_cost')
            ->groupBy('vehicle_id')
            ->get();

        $jobCount = [];
        $jobCost = [];
        foreach ($jobs as $job) {
            $unitId = $unitByVehicle[$job->vehicle_id] ?? 0;
            $jobCount[$unitId] = ($jobCount[$unitId] ?? 0) + (int) $job->job_count;
            $jobCost[$unitId] = ($jobCost[$unitId] ?? 0) + (float) $job->job_cost;
        }

        $fuelCost = [];
        foreach ($fuel as $entry) {
            $unitId = $unitByVehicle[$entry->vehicle_id] ?? 0;
            $fuelCost[$unitId] = ($fuelCost[$unitId] ?? 0) + (float) $entry->fuel_cost;
        }

        $rows = [];
        $sums = array_fill(0, 9, 0);

        foreach ($vehiclesByUnit as $unitId => $group) {
            $disposed = $group->filter(fn (Vehicle $v) => $v->isDisposed())->count();
            $active = $group->reject(fn (Vehicle $v) => $v->isDisposed());

            $row = [
                $unitId === 0 ? 'No unit assigned' : $this->unitLabel($group->first()),
                $active->count(),
                $active->where('status', 'SERVICEABLE')->count(),
                $active->where('status', 'UNSERVICEABLE')->count(),
                $active->where('status', 'BER')->count(),
                $disposed,
                $active->filter(fn (Vehicle $v) => $v->needsRegistrationAlert())->count(),
                $jobCount[$unitId] ?? 0,
                $jobCost[$unitId] ?? 0.0,
                $fuelCost[$unitId] ?? 0.0,
            ];

            foreach (range(1, 9) as $i) {
                $sums[$i - 1] += $row[$i];
            }

            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => strcmp((string) $a[0], (string) $b[0]));

        return [
            'columns' => [
                ['label' => 'Unit', 'type' => 'text'],
                ['label' => 'Vehicles (excl. disposed)', 'type' => 'int'],
                ['label' => 'Serviceable', 'type' => 'int'],
                ['label' => 'Unserviceable', 'type' => 'int'],
                ['label' => 'BER', 'type' => 'int'],
                ['label' => 'Disposed', 'type' => 'int'],
                ['label' => 'Registration Due / Expired', 'type' => 'int'],
                ['label' => 'Repair & Maint. Jobs', 'type' => 'int'],
                ['label' => 'Repair & Maint. Cost (PHP)', 'type' => 'money'],
                ['label' => 'Fuel Cost (PHP)', 'type' => 'money'],
            ],
            'rows'   => $rows,
            'totals' => $rows ? array_merge(['TOTAL'], $sums) : null,
        ];
    }

    protected function fuelSummary(array $filters, $user, string $role): array
    {
        $vehicles = $this->vehicleQuery($filters, $user, $role)
            ->with('unit')
            ->orderBy('plate_number')
            ->get()
            ->keyBy('id');

        $logs = FuelLog::query()
            ->whereIn('vehicle_id', $vehicles->keys())
            ->tap(fn ($q) => $this->applyDates($q, $filters, 'refuel_date'))
            ->orderBy('refuel_date')
            ->orderBy('id')
            ->get(['vehicle_id', 'refuel_date', 'liters', 'total_cost', 'odometer_reading'])
            ->groupBy('vehicle_id');

        $rows = [];
        $totalRefuels = 0;
        $totalLiters = 0.0;
        $totalCost = 0.0;

        foreach ($vehicles as $vehicleId => $vehicle) {
            $group = $logs->get($vehicleId);
            if (! $group || $group->isEmpty()) {
                continue;
            }

            $liters = (float) $group->sum('liters');
            $cost = (float) $group->sum('total_cost');
            $count = $group->count();

            // Efficiency compares distance driven since the first fill-up in
            // the range with the fuel put in AFTER it, so the first fill
            // (whose preceding distance is unknown) is left out.
            $distance = null;
            $kmPerLiter = null;
            if ($count >= 2) {
                $distance = (int) $group->last()->odometer_reading - (int) $group->first()->odometer_reading;
                $litersAfterFirst = $liters - (float) $group->first()->liters;
                if ($distance > 0 && $litersAfterFirst > 0) {
                    $kmPerLiter = $distance / $litersAfterFirst;
                } else {
                    $distance = $distance > 0 ? $distance : null;
                }
            }

            $rows[] = [
                strtoupper($vehicle->plate_number),
                $this->unitLabel($vehicle),
                $count,
                $liters,
                $cost,
                $liters > 0 ? $cost / $liters : null,
                $distance,
                $kmPerLiter,
            ];

            $totalRefuels += $count;
            $totalLiters += $liters;
            $totalCost += $cost;
        }

        return [
            'columns' => [
                ['label' => 'Plate No.', 'type' => 'text'],
                ['label' => 'Unit', 'type' => 'text'],
                ['label' => 'Refuels', 'type' => 'int'],
                ['label' => 'Liters', 'type' => 'dec2'],
                ['label' => 'Total Cost (PHP)', 'type' => 'money'],
                ['label' => 'Avg. Price / L (PHP)', 'type' => 'money'],
                ['label' => 'Distance (km)', 'type' => 'int'],
                ['label' => 'Efficiency (km/L)', 'type' => 'dec2'],
            ],
            'rows'   => $rows,
            'totals' => $rows ? [
                'TOTAL', null, $totalRefuels, $totalLiters, $totalCost,
                $totalLiters > 0 ? $totalCost / $totalLiters : null, null, null,
            ] : null,
        ];
    }

    protected function tripSummary(array $filters, $user, string $role): array
    {
        $vehicles = $this->vehicleQuery($filters, $user, $role)
            ->with('unit')
            ->orderBy('plate_number')
            ->get()
            ->keyBy('id');

        $trips = TripLog::query()
            ->whereIn('vehicle_id', $vehicles->keys())
            ->tap(fn ($q) => $this->applyDates($q, $filters, 'trip_date'))
            ->get(['vehicle_id', 'distance_km', 'odometer_start', 'odometer_end', 'max_speed_kmh'])
            ->groupBy('vehicle_id');

        $rows = [];
        $totalTrips = 0;
        $totalDistance = 0.0;
        $topSpeed = null;

        foreach ($vehicles as $vehicleId => $vehicle) {
            $group = $trips->get($vehicleId);
            if (! $group || $group->isEmpty()) {
                continue;
            }

            // GPS-tracked trips store distance_km; manually logged ones only
            // have odometer readings, so fall back to end - start for those.
            $distance = 0.0;
            foreach ($group as $trip) {
                if ($trip->distance_km !== null) {
                    $distance += (float) $trip->distance_km;
                } elseif ($trip->odometer_start !== null && $trip->odometer_end !== null && $trip->odometer_end >= $trip->odometer_start) {
                    $distance += (float) ($trip->odometer_end - $trip->odometer_start);
                }
            }

            $maxSpeed = $group->max('max_speed_kmh');

            $rows[] = [
                strtoupper($vehicle->plate_number),
                $this->unitLabel($vehicle),
                $group->count(),
                $distance,
                $maxSpeed !== null ? (float) $maxSpeed : null,
            ];

            $totalTrips += $group->count();
            $totalDistance += $distance;
            if ($maxSpeed !== null && ($topSpeed === null || (float) $maxSpeed > $topSpeed)) {
                $topSpeed = (float) $maxSpeed;
            }
        }

        return [
            'columns' => [
                ['label' => 'Plate No.', 'type' => 'text'],
                ['label' => 'Unit', 'type' => 'text'],
                ['label' => 'Trips', 'type' => 'int'],
                ['label' => 'Distance (km)', 'type' => 'dec1'],
                ['label' => 'Top Speed (km/h)', 'type' => 'dec1'],
            ],
            'rows'   => $rows,
            'totals' => $rows ? ['TOTAL', null, $totalTrips, $totalDistance, $topSpeed] : null,
        ];
    }

    // ------------------------------------------------------------------
    // Output helpers
    // ------------------------------------------------------------------

    protected function statusLabel(Vehicle $vehicle): string
    {
        $label = self::STATUS_LABELS[$vehicle->status] ?? (string) $vehicle->status;

        if ($vehicle->status === 'BER' && $vehicle->ber_sub_status) {
            $label .= ' - ' . (Vehicle::BER_SUB_STATUSES[$vehicle->ber_sub_status] ?? $vehicle->ber_sub_status);
        }

        return $label;
    }

    protected function unitLabel(Vehicle $vehicle): ?string
    {
        $unit = $vehicle->unit;

        return $unit ? ($unit->unit_abbvr ?: $unit->unit_name) : null;
    }

    protected function personName($person): ?string
    {
        if (! $person) {
            return null;
        }

        $name = trim(implode(' ', array_filter([
            $person->rank, $person->firstname, $person->lastname,
        ])));

        return $name !== '' ? $name : null;
    }

    /**
     * Human-readable list of the filters in effect, printed on the report
     * header so a printed copy always says what it covers.
     *
     * @return array<int, string>
     */
    protected function describeFilters(array $filters, bool $usesDates): array
    {
        $lines = [];

        if (! empty($filters['unit_id'])) {
            $lines[] = 'Unit: ' . optional(Unit::find($filters['unit_id']))->unit_name;
        }
        if (! empty($filters['station_id'])) {
            $lines[] = 'Station: ' . optional(Station::find($filters['station_id']))->station_name;
        }
        if (! empty($filters['status'])) {
            $lines[] = 'Status: ' . self::STATUS_LABELS[$filters['status']];
        }
        if (! empty($filters['maintenance_type'])) {
            $lines[] = 'Type: ' . MaintenanceRecord::TYPES[$filters['maintenance_type']];
        }
        if ($usesDates && (! empty($filters['date_from']) || ! empty($filters['date_to']))) {
            $lines[] = 'Period: ' . ($filters['date_from'] ?? 'start') . ' to ' . ($filters['date_to'] ?? 'today');
        }

        return $lines ?: ['All records within your access'];
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<int, array{label: string, type: string}>  $columns
     * @return array<int, string>
     */
    protected function formatRow(array $row, array $columns, bool $forCsv): array
    {
        $formatted = [];
        foreach ($columns as $index => $column) {
            $formatted[] = $this->formatCell($row[$index] ?? null, $column['type'], $forCsv);
        }

        return $formatted;
    }

    protected function formatCell(mixed $value, string $type, bool $forCsv): string
    {
        if ($value === null || $value === '') {
            return $forCsv ? '' : '—';
        }

        if ($type === 'text') {
            $text = (string) $value;

            // Stop spreadsheet formulas: Excel runs a cell starting with these.
            if ($forCsv && preg_match('/^[=+\-@\t\r]/', $text)) {
                return "'" . $text;
            }

            return $text;
        }

        $number = (float) $value;
        $decimals = match ($type) {
            'money', 'dec2' => 2,
            'dec1'          => 1,
            default         => 0,
        };

        return $forCsv
            ? number_format($number, $decimals, '.', '')
            : number_format($number, $decimals);
    }

    protected function renderCsv(string $report, array $data, array $meta): StreamedResponse
    {
        $filename = $report . '-' . $meta['generated_at']->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($data, $meta) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel reads the file as UTF-8 (peso sign, names).
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [$meta['title']]);
            fputcsv($out, ['Generated', $meta['generated_at']->format('Y-m-d H:i'), 'By', $meta['generated_by']]);
            fputcsv($out, ['Scope', implode('; ', $meta['scope'])]);
            fputcsv($out, []);
            fputcsv($out, array_column($data['columns'], 'label'));

            foreach ($data['rows'] as $row) {
                fputcsv($out, $this->formatRow($row, $data['columns'], true));
            }

            if ($data['totals']) {
                fputcsv($out, $this->formatRow($data['totals'], $data['columns'], true));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
