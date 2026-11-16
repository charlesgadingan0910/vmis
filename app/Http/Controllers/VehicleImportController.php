<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Driver;
use App\Models\Station;
use App\Models\Unit;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Support\SimpleXlsx;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * P1-extra — Bulk import of vehicle records from an Excel (.xlsx) or CSV file.
 *
 * Two-step flow: "Check file" (preview=1) validates every row and reports
 * problems without saving anything; "Import" saves the valid rows. Rows that
 * fail validation, or whose plate already exists, are skipped and reported —
 * they never block the good rows. Unit/station scoping mirrors
 * VehicleController::applyWriteScope(): a Unit Administrator can only import
 * into their own unit, a Station Administrator only into their own station.
 */
class VehicleImportController extends Controller
{
    public const MAX_ROWS = 1000;
    public const MAX_REPORTED = 300;

    protected const IMPORT_ROLES = ['SUPER ADMINISTRATOR', 'ADMINISTRATOR', 'UNIT ADMINISTRATOR', 'STATION ADMINISTRATOR'];

    /** Template column => [label shown in the template, header aliases (normalized)]. */
    protected const COLUMNS = [
        'plate_number'         => ['Plate Number', ['platenumber', 'plate', 'plateno']],
        'make'                 => ['Make', ['make', 'brand']],
        'model'                => ['Model', ['model']],
        'variant'              => ['Variant', ['variant']],
        'vehicle_type'         => ['Vehicle Type', ['vehicletype', 'type']],
        'unit'                 => ['Unit', ['unit', 'unitname']],
        'station'              => ['Station', ['station', 'stationname']],
        'source'               => ['Source', ['source']],
        'status'               => ['Status', ['status']],
        'year_model'           => ['Year Model', ['yearmodel', 'year']],
        'color'                => ['Color', ['color', 'colour']],
        'marked_status'        => ['Marked/Unmarked', ['markedunmarked', 'marked', 'marking', 'markedstatus']],
        'engine_number'        => ['Engine Number', ['enginenumber', 'engineno', 'engine']],
        'chassis_number'       => ['Chassis Number', ['chassisnumber', 'chassisno', 'chassis']],
        'par_driver'           => ['Primary PAR Driver', ['primarypardriver', 'pardriver', 'primarypar']],
        'par_driver_2'         => ['Alternate PAR Driver', ['alternatepardriver', 'alternatepar', 'pardriver2']],
        'acquisition_date'     => ['Acquisition Date', ['acquisitiondate', 'dateacquired']],
        'odometer_km'          => ['Odometer (km)', ['odometerkm', 'odometer']],
        'next_pms_date'        => ['Next PMS Date', ['nextpmsdate', 'pmsdate']],
        'next_pms_odometer_km' => ['Next PMS Odometer (km)', ['nextpmsodometerkm', 'nextpmsodometer']],
    ];

    protected const REQUIRED = ['plate_number', 'make', 'model', 'vehicle_type'];

    public function template(): BinaryFileResponse
    {
        $user = $this->authorizeImport();
        $role = $this->role($user);

        $units = $this->scopedUnits($user, $role);
        $stations = $this->scopedStations($user, $role);

        $headers = array_map(fn ($c) => $c[0], array_values(self::COLUMNS));
        $example = [
            'plate_number' => 'ABC 1234', 'make' => 'TOYOTA', 'model' => 'HILUX', 'variant' => '4X2 MT',
            'vehicle_type' => optional(VehicleType::orderBy('name')->first())->name ?? 'Patrol Car',
            'unit' => optional($units->first())->unit_name ?? '', 'station' => optional($stations->first())->station_name ?? '',
            'source' => 'Organic', 'status' => 'Serviceable', 'year_model' => '2022', 'color' => 'White',
            'marked_status' => 'Marked', 'acquisition_date' => '2022-03-15', 'odometer_km' => '15000',
            'next_pms_date' => '2026-12-01', 'next_pms_odometer_km' => '20000',
        ];
        $exampleRow = array_map(fn ($key) => $example[$key] ?? '', array_keys(self::COLUMNS));

        $guide = [
            ['Column', 'Required?', 'Notes'],
            ['Plate Number', 'Yes', 'Must be new — existing plates are skipped.'],
            ['Make / Model', 'Yes', 'Saved in capital letters.'],
            ['Vehicle Type', 'Yes', 'Pick from the dropdown.'],
            ['Unit', 'Admins only', 'Pick from the dropdown. Unit/Station administrators: leave blank — your own unit is used.'],
            ['Station', 'Yes (except Station Admin)', 'Pick the Unit first, then choose the Station from the dropdown (it only lists that unit\'s stations). Station administrators: leave blank.'],
            ['Source', 'No', 'Organic, Loaned or Donated. Blank = Organic.'],
            ['Status', 'No', 'Serviceable, Unserviceable or BER (saved as "For Disposal"). Blank = Serviceable.'],
            ['Variant', 'No', 'Free text, e.g. 4X2 MT. Saved in capital letters.'],
            ['Marked/Unmarked', 'No', 'Pick Marked or Unmarked from the dropdown. Blank = not specified.'],
            ['Primary / Alternate PAR Driver', 'No', 'Pick from the dropdown (drivers in the system). The two cannot be the same person. These are separate from the assigned drivers.'],
            ['Year Model', 'No', '1980 up to next year.'],
            ['Dates', 'No', 'Use YYYY-MM-DD (e.g. 2026-12-31) or real Excel dates.'],
            ['Engine / Chassis Number', 'No', 'Must be unique across the fleet.'],
            ['Limits', '', 'Up to ' . self::MAX_ROWS . ' rows per file. Do not rename the column headings.'],
        ];

        // Reference sheet = the source of the template's dropdown lists.
        //   A: Unit   B: that unit's range name   C: Station (grouped by unit)
        //   D: Vehicle Type   E: Source   F: Status   G: Marked/Unmarked   H: Driver (PAR dropdowns)
        // Each unit with stations gets a defined name (Stn_<id>) covering its stations in
        // column C; the Station dropdown resolves it with INDIRECT(VLOOKUP(unit)).
        $types = VehicleType::orderBy('name')->pluck('name')->all();
        $sources = array_values(Vehicle::SOURCES);
        $statuses = ['Serviceable', 'Unserviceable', 'BER'];
        $markings = array_values(Vehicle::MARKED_STATUSES);
        $driverNames = Driver::where('status', 'active')->orderBy('lastname')->orderBy('firstname')->get()
            ->map(fn ($d) => $this->driverLabel($d))->filter()->values()->all();

        $stationNames = [];
        $definedNames = [];
        $unitKeys = [];
        $row = 2;
        foreach ($units as $unit) {
            $own = $stations->where('unit_id', $unit->id)->sortBy(fn ($s) => Str::lower($s->station_name));
            if ($own->isEmpty()) {
                $unitKeys[$unit->id] = '';
                continue;
            }
            $key = 'Stn_' . $unit->id;
            $unitKeys[$unit->id] = $key;
            $start = $row;
            foreach ($own as $station) {
                $stationNames[] = $station->station_name;
                $row++;
            }
            $definedNames[$key] = 'Reference!$C$' . $start . ':$C$' . ($row - 1);
        }

        $unitList = $units->values();
        $reference = [['Unit', 'Range name', 'Station', 'Vehicle Type', 'Source', 'Status', 'Marked/Unmarked', 'Driver']];
        $count = max(count($types), $unitList->count(), count($stationNames), count($sources), count($statuses), count($markings), count($driverNames));
        for ($i = 0; $i < $count; $i++) {
            $unit = $unitList[$i] ?? null;
            $reference[] = [
                $unit->unit_name ?? '', $unit ? $unitKeys[$unit->id] : '', $stationNames[$i] ?? '',
                $types[$i] ?? '', $sources[$i] ?? '', $statuses[$i] ?? '', $markings[$i] ?? '', $driverNames[$i] ?? '',
            ];
        }

        $last = fn (int $n) => max(2, $n + 1);
        $range = fn (string $col, int $n) => 'Reference!$' . $col . '$2:$' . $col . '$' . $last($n);
        $unitTable = 'Reference!$A$2:$B$' . $last($unitList->count());
        // Station dropdown follows the chosen Unit (all stations while Unit is still blank).
        $letter = fn (string $key) => $this->columnLetter(array_search($key, array_keys(self::COLUMNS), true));
        $unitCol = $letter('unit');
        $stationFormula = 'IF($' . $unitCol . '2="",' . $range('C', count($stationNames)) . ',INDIRECT(VLOOKUP($' . $unitCol . '2,' . $unitTable . ',2,FALSE)))';
        $rows = self::MAX_ROWS + 1;

        $path = tempnam(sys_get_temp_dir(), 'vmis_tpl_');
        SimpleXlsx::write($path, [
            'Vehicles'  => [$headers, $exampleRow],
            'Guide'     => $guide,
            'Reference' => $reference,
        ], [
            'Vehicles'  => array_fill(0, count($headers), 22),
            'Guide'     => [26, 26, 80],
            'Reference' => [36, 14, 44, 24, 14, 16, 18, 36],
        ], [
            'Vehicles' => [
                $this->dropdown($letter('vehicle_type'), $rows, $range('D', count($types))),
                $this->dropdown($unitCol, $rows, $range('A', $unitList->count())),
                $this->dropdown($letter('station'), $rows, $stationFormula),
                $this->dropdown($letter('source'), $rows, $range('E', count($sources))),
                $this->dropdown($letter('status'), $rows, $range('F', count($statuses))),
                $this->dropdown($letter('marked_status'), $rows, $range('G', count($markings))),
                $this->dropdown($letter('par_driver'), $rows, $range('H', count($driverNames))),
                $this->dropdown($letter('par_driver_2'), $rows, $range('H', count($driverNames))),
            ],
        ], $definedNames, ['Reference']);

        return response()->download($path, 'vmis-vehicle-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request): JsonResponse
    {
        $user = $this->authorizeImport();
        $role = $this->role($user);

        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ], [
            'file.required' => 'Choose an Excel (.xlsx) or CSV file first.',
            'file.max'      => 'The file is larger than 5 MB.',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true)) {
            return response()->json(['message' => 'Only .xlsx or .csv files are accepted. Please download and use the template.'], 422);
        }

        try {
            $grid = $extension === 'csv' ? $this->readCsv($file->getRealPath()) : SimpleXlsx::read($file->getRealPath(), self::MAX_ROWS + 50);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage() ?: 'The file could not be read.'], 422);
        }

        $headerRowNo = array_key_first($grid);
        if ($headerRowNo === null) {
            return response()->json(['message' => 'The file is empty.'], 422);
        }

        $map = $this->mapHeaders($grid[$headerRowNo]);
        $missing = array_diff(self::REQUIRED, array_keys($map));
        if ($missing) {
            $labels = array_map(fn ($k) => self::COLUMNS[$k][0], $missing);

            return response()->json(['message' => 'Missing required column(s): ' . implode(', ', $labels) . '. Please use the downloadable template.'], 422);
        }

        unset($grid[$headerRowNo]);
        $dataRows = [];
        foreach ($grid as $rowNo => $cells) {
            $row = [];
            foreach ($map as $key => $col) {
                $row[$key] = trim((string) ($cells[$col] ?? ''));
            }
            if (implode('', $row) === '') {
                continue; // blank spacer row
            }
            $dataRows[$rowNo + 1] = $row;
        }

        if (! $dataRows) {
            return response()->json(['message' => 'No vehicle rows found under the header row.'], 422);
        }
        if (count($dataRows) > self::MAX_ROWS) {
            return response()->json(['message' => 'The file has ' . count($dataRows) . ' rows; the limit is ' . self::MAX_ROWS . ' per upload. Please split it.'], 422);
        }

        $ctx = $this->lookups();
        $existing = $this->existingKeys($dataRows);
        $seen = ['plate' => [], 'engine' => [], 'chassis' => []];

        $valid = [];
        $problems = [];
        $counts = ['ok' => 0, 'error' => 0, 'duplicate' => 0];

        foreach ($dataRows as $rowNo => $row) {
            [$data, $errors] = $this->prepareRow($row, $user, $role, $ctx);

            $plate = $data['plate_number'] ?? '';
            if ($plate !== '' && isset($existing['plate'][$plate])) {
                $counts['duplicate']++;
                $problems[] = ['row' => $rowNo, 'plate' => $plate, 'type' => 'duplicate', 'messages' => ['Plate already exists in the system — skipped.']];
                continue;
            }
            if ($plate !== '' && isset($seen['plate'][$plate])) {
                $errors[] = 'Plate appears more than once in this file (first on row ' . $seen['plate'][$plate] . ').';
            }
            foreach (['engine_number' => 'engine', 'chassis_number' => 'chassis'] as $field => $bag) {
                $v = $data[$field] ?? null;
                if ($v === null) {
                    continue;
                }
                if (isset($existing[$bag][$v])) {
                    $errors[] = ucfirst($bag) . ' number already belongs to another vehicle.';
                } elseif (isset($seen[$bag][$v])) {
                    $errors[] = ucfirst($bag) . ' number repeats row ' . $seen[$bag][$v] . '.';
                }
            }

            if ($errors) {
                $counts['error']++;
                $problems[] = ['row' => $rowNo, 'plate' => $plate, 'type' => 'error', 'messages' => array_values(array_unique($errors))];
                continue;
            }

            $seen['plate'][$plate] = $rowNo;
            foreach (['engine_number' => 'engine', 'chassis_number' => 'chassis'] as $field => $bag) {
                if (! empty($data[$field])) {
                    $seen[$bag][$data[$field]] = $rowNo;
                }
            }

            $counts['ok']++;
            $valid[] = $data;
        }

        $preview = $request->boolean('preview');
        $imported = 0;

        if (! $preview && $valid) {
            $imported = $this->persist($valid, $user);

            ActivityLog::record(
                'imported',
                'Vehicle',
                'Imported ' . $imported . ' vehicle(s) from file "' . $file->getClientOriginalName() . '" ('
                    . $counts['error'] . ' row(s) with errors, ' . $counts['duplicate'] . ' duplicate plate(s) skipped).',
                null,
                ['after' => ['file' => $file->getClientOriginalName(), 'imported' => $imported, 'errors' => $counts['error'], 'duplicates' => $counts['duplicate']]]
            );
        }

        return response()->json([
            'preview'   => $preview,
            'total'     => count($dataRows),
            'ready'     => $counts['ok'],
            'errors'    => $counts['error'],
            'duplicates' => $counts['duplicate'],
            'imported'  => $imported,
            'problems'  => array_slice($problems, 0, self::MAX_REPORTED),
            'truncated' => count($problems) > self::MAX_REPORTED,
        ]);
    }

    /**
     * Normalizes one spreadsheet row into a Vehicle payload.
     *
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    protected function prepareRow(array $row, $user, string $role, array $ctx): array
    {
        $errors = [];

        $data = [
            'plate_number'         => Str::upper(preg_replace('/\s+/', ' ', $row['plate_number'] ?? '')),
            'make'                 => Str::upper($row['make'] ?? ''),
            'model'                => Str::upper($row['model'] ?? ''),
            'color'                => $this->nullable($row['color'] ?? ''),
            'variant'              => $this->nullable(Str::upper($row['variant'] ?? '')),
            'engine_number'        => $this->nullable(Str::upper($row['engine_number'] ?? '')),
            'chassis_number'       => $this->nullable(Str::upper($row['chassis_number'] ?? '')),
            'year_model'           => $this->wholeNumber($row['year_model'] ?? ''),
            'odometer_km'          => $this->wholeNumber($row['odometer_km'] ?? ''),
            'next_pms_odometer_km' => $this->wholeNumber($row['next_pms_odometer_km'] ?? ''),
        ];

        foreach (['acquisition_date', 'next_pms_date'] as $field) {
            [$data[$field], $dateError] = $this->parseDate($row[$field] ?? '');
            if ($dateError) {
                $errors[] = self::COLUMNS[$field][0] . ': ' . $dateError;
            }
        }

        // Vehicle type.
        $typeName = Str::lower($row['vehicle_type'] ?? '');
        $type = $typeName !== '' ? ($ctx['types'][$typeName] ?? null) : null;
        if (! $type) {
            $errors[] = $typeName === '' ? 'Vehicle Type is required.' : 'Unknown Vehicle Type "' . $row['vehicle_type'] . '" (pick it from the dropdown in the template).';
        }
        $data['vehicle_type_id'] = $type;

        // Source / status with friendly defaults.
        $source = Str::upper(trim($row['source'] ?? ''));
        $data['source'] = $source === '' ? 'ORGANIC' : $source;
        $status = Str::upper(trim($row['status'] ?? ''));
        $data['status'] = $status === '' ? 'SERVICEABLE' : $status;

        // Marked / Unmarked (optional).
        $marked = Str::upper(trim($row['marked_status'] ?? ''));
        $data['marked_status'] = $marked === '' ? null : $marked;

        // PAR drivers (optional) — matched by name against active drivers.
        foreach (['par_driver' => 'par_driver_id', 'par_driver_2' => 'par_driver_id_2'] as $column => $field) {
            $name = Str::lower(preg_replace('/\s+/', ' ', trim($row[$column] ?? '')));
            $data[$field] = null;
            if ($name === '') {
                continue;
            }
            $matches = $ctx['drivers'][$name] ?? [];
            if (count($matches) === 1) {
                $data[$field] = $matches[0];
            } elseif (count($matches) > 1) {
                $errors[] = self::COLUMNS[$column][0] . ': "' . $row[$column] . '" matches more than one driver.';
            } else {
                $errors[] = self::COLUMNS[$column][0] . ': "' . $row[$column] . '" is not an active driver (pick it from the dropdown in the template).';
            }
        }
        if ($data['par_driver_id'] && $data['par_driver_id'] === $data['par_driver_id_2']) {
            $errors[] = 'Primary and Alternate PAR Driver cannot be the same person.';
        }

        // Unit & station, scoped by role.
        [$unitId, $stationId, $scopeErrors] = $this->resolveLocation($row, $user, $role, $ctx);
        array_push($errors, ...$scopeErrors);
        $data['unit_id'] = $unitId;
        $data['station_id'] = $stationId;

        $validator = Validator::make($data, [
            'plate_number'         => ['required', 'string', 'max:20'],
            'engine_number'        => ['nullable', 'string', 'max:50'],
            'chassis_number'       => ['nullable', 'string', 'max:50'],
            'make'                 => ['required', 'string', 'max:50'],
            'model'                => ['required', 'string', 'max:50'],
            'year_model'           => ['nullable', 'integer', 'min:1980', 'max:' . (date('Y') + 1)],
            'color'                => ['nullable', 'string', 'max:30'],
            'source'               => ['required', 'in:ORGANIC,LOANED,DONATED'],
            'odometer_km'          => ['nullable', 'integer', 'min:0'],
            'next_pms_odometer_km' => ['nullable', 'integer', 'min:0'],
            'status'               => ['required', 'in:SERVICEABLE,UNSERVICEABLE,BER'],
            'variant'              => ['nullable', 'string', 'max:50'],
            'marked_status'        => ['nullable', 'in:MARKED,UNMARKED'],
        ], [], [
            'variant' => 'Variant', 'marked_status' => 'Marked/Unmarked',
            'plate_number' => 'Plate Number', 'make' => 'Make', 'model' => 'Model', 'year_model' => 'Year Model',
            'color' => 'Color', 'source' => 'Source', 'odometer_km' => 'Odometer', 'engine_number' => 'Engine Number',
            'chassis_number' => 'Chassis Number', 'status' => 'Status', 'next_pms_odometer_km' => 'Next PMS Odometer',
        ]);
        foreach ($validator->errors()->all() as $message) {
            $errors[] = $message;
        }

        return [$data, $errors];
    }

    /** @return array{0: ?int, 1: ?int, 2: array<int, string>} */
    protected function resolveLocation(array $row, $user, string $role, array $ctx): array
    {
        $errors = [];
        $unitText = Str::lower($row['unit'] ?? '');
        $stationText = Str::lower($row['station'] ?? '');

        if ($role === 'STATION ADMINISTRATOR') {
            foreach ([['unit', $unitText, $ctx['units'], $user->unit_id], ['station', $stationText, $ctx['stations'], $user->station_id]] as [$label, $text, $lookup, $own]) {
                if ($text !== '') {
                    $found = $lookup[$text] ?? null;
                    $found = is_array($found) ? ($found[0] ?? null) : $found;
                    if ((int) $found !== (int) $own) {
                        $errors[] = 'You can only import vehicles for your own ' . $label . '.';
                    }
                }
            }

            return [$user->unit_id, $user->station_id, $errors];
        }

        // Unit.
        $unitId = null;
        if ($role === 'UNIT ADMINISTRATOR') {
            $unitId = $user->unit_id;
            if ($unitText !== '' && (int) ($ctx['units'][$unitText] ?? 0) !== (int) $unitId) {
                $errors[] = 'You can only import vehicles for your own unit.';
            }
        } elseif ($unitText !== '') {
            $unitId = $ctx['units'][$unitText] ?? null;
            if (! $unitId) {
                $errors[] = 'Unknown Unit "' . $row['unit'] . '" (pick it from the dropdown in the template).';
            }
        }

        // Station (may be duplicated across units, so keep candidate lists).
        $stationId = null;
        if ($stationText === '') {
            $errors[] = 'Station is required.';
        } else {
            $candidates = $ctx['stations'][$stationText] ?? [];
            $candidates = is_array($candidates) ? $candidates : [$candidates];
            if ($unitId) {
                $candidates = array_values(array_filter($candidates, fn ($c) => (int) ($ctx['stationUnit'][$c] ?? 0) === (int) $unitId));
            }
            if (count($candidates) === 1) {
                $stationId = $candidates[0];
                $unitId = $unitId ?: ($ctx['stationUnit'][$stationId] ?? null);
            } elseif (count($candidates) > 1) {
                $errors[] = 'Station "' . $row['station'] . '" exists in several units — fill in the Unit column.';
            } else {
                $errors[] = 'Station "' . $row['station'] . '" was not found' . ($unitId ? ' in that unit' : '') . ' (pick it from the dropdown in the template).';
            }
        }

        if (! $unitId && ! $errors) {
            $errors[] = 'Unit is required.';
        }

        return [$unitId, $stationId, $errors];
    }

    /** @param array<int, array<string, mixed>> $rows */
    protected function persist(array $rows, $user): int
    {
        return DB::transaction(function () use ($rows, $user) {
            $count = 0;
            foreach ($rows as $data) {
                // vehicles.odometer_km is NOT NULL (default 0), so a blank cell must become 0, not null.
                $data['odometer_km'] = $data['odometer_km'] ?? 0;
                $data['unserviceable_since'] = $data['status'] === 'UNSERVICEABLE' ? now() : null;
                $data['ber_sub_status'] = $data['status'] === 'BER' ? 'FOR_DISPOSAL' : null;
                $data['disposal_date'] = null;
                $data['qr_code'] = $this->uniqueQrCode();
                $data['is_active'] = '1';
                $data['encoded_by'] = $user->id;

                Vehicle::create($data);
                $count++;
            }

            return $count;
        });
    }

    protected function uniqueQrCode(): string
    {
        do {
            $code = Str::upper(Str::random(10));
        } while (Vehicle::where('qr_code', $code)->exists());

        return $code;
    }

    /** @return array{drivers: array<string,array<int,int>>, types: array<string,int>, units: array<string,int>, stations: array<string,array<int,int>>, stationUnit: array<int,int>} */
    protected function lookups(): array
    {
        $types = [];
        foreach (VehicleType::all(['id', 'name']) as $t) {
            $types[Str::lower(trim($t->name))] = $t->id;
        }

        $units = [];
        foreach (Unit::all(['id', 'unit_name', 'unit_abbvr']) as $u) {
            foreach ([$u->unit_name, $u->unit_abbvr] as $label) {
                if ($label) {
                    $units[Str::lower(trim($label))] = $u->id;
                }
            }
        }

        $stations = [];
        $stationUnit = [];
        foreach (Station::all(['id', 'unit_id', 'station_name', 'station_abbvr']) as $s) {
            $stationUnit[$s->id] = $s->unit_id;
            foreach ([$s->station_name, $s->station_abbvr] as $label) {
                if ($label) {
                    $key = Str::lower(trim($label));
                    $stations[$key] = array_values(array_unique(array_merge($stations[$key] ?? [], [$s->id])));
                }
            }
        }

        // Active drivers by name — "Firstname Lastname" and "Lastname, Firstname" both match.
        $drivers = [];
        foreach (Driver::where('status', 'active')->get(['id', 'firstname', 'lastname']) as $d) {
            foreach ([trim($d->firstname . ' ' . $d->lastname), trim($d->lastname . ', ' . $d->firstname)] as $label) {
                $key = Str::lower(preg_replace('/\s+/', ' ', $label));
                $drivers[$key] = array_values(array_unique(array_merge($drivers[$key] ?? [], [$d->id])));
            }
        }

        return compact('types', 'units', 'stations', 'stationUnit', 'drivers');
    }

    /** @return array{plate: array<string,bool>, engine: array<string,bool>, chassis: array<string,bool>} */
    protected function existingKeys(array $dataRows): array
    {
        $plates = $engines = $chassis = [];
        foreach ($dataRows as $row) {
            if (($p = Str::upper(preg_replace('/\s+/', ' ', $row['plate_number'] ?? ''))) !== '') {
                $plates[] = $p;
            }
            if (($e = Str::upper($row['engine_number'] ?? '')) !== '') {
                $engines[] = $e;
            }
            if (($c = Str::upper($row['chassis_number'] ?? '')) !== '') {
                $chassis[] = $c;
            }
        }

        $out = ['plate' => [], 'engine' => [], 'chassis' => []];
        foreach (array_chunk(array_unique($plates), 500) as $chunk) {
            foreach (Vehicle::whereIn('plate_number', $chunk)->pluck('plate_number') as $v) {
                $out['plate'][Str::upper($v)] = true;
            }
        }
        foreach (array_chunk(array_unique($engines), 500) as $chunk) {
            foreach (Vehicle::whereIn('engine_number', $chunk)->pluck('engine_number') as $v) {
                $out['engine'][Str::upper($v)] = true;
            }
        }
        foreach (array_chunk(array_unique($chassis), 500) as $chunk) {
            foreach (Vehicle::whereIn('chassis_number', $chunk)->pluck('chassis_number') as $v) {
                $out['chassis'][Str::upper($v)] = true;
            }
        }

        return $out;
    }

    /** @return array<string, int> header key => column index */
    protected function mapHeaders(array $headerCells): array
    {
        $map = [];
        foreach ($headerCells as $index => $cell) {
            $normalized = preg_replace('/[^a-z0-9]/', '', Str::lower((string) $cell));
            if ($normalized === '') {
                continue;
            }
            foreach (self::COLUMNS as $key => [, $aliases]) {
                if (! isset($map[$key]) && in_array($normalized, $aliases, true)) {
                    $map[$key] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    /** @return array<int, array<int, string>> */
    protected function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            throw new \RuntimeException('The file could not be opened.');
        }

        $n = 0;
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $n++;
            if ($n === 1 && isset($line[0])) {
                $line[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $line[0]); // UTF-8 BOM
            }
            $rows[$n - 1] = array_map(fn ($v) => (string) $v, $line);
            if ($n > self::MAX_ROWS + 50) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    /** @return array{sqref: string, formula: string} */
    protected function dropdown(string $column, int $lastRow, string $formula): array
    {
        return ['sqref' => $column . '2:' . $column . $lastRow, 'formula' => $formula];
    }

    /** 0 => A, 25 => Z, 26 => AA */
    protected function columnLetter(int $index): string
    {
        $letters = '';
        for ($i = $index + 1; $i > 0; $i = intdiv($i - 1, 26)) {
            $letters = chr(65 + (($i - 1) % 26)) . $letters;
        }

        return $letters;
    }

    protected function driverLabel(Driver $driver): string
    {
        return trim($driver->firstname . ' ' . $driver->lastname);
    }

    protected function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** "2020", "2020.0", "15,000" => int|string (left as-is when not numeric so validation flags it). */
    protected function wholeNumber(string $value)
    {
        $value = trim(str_replace(',', '', $value));
        if ($value === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value == floor((float) $value)) {
            return (int) $value;
        }

        return $value;
    }

    /** @return array{0: ?string, 1: ?string} [Y-m-d|null, error|null] */
    protected function parseDate(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [null, null];
        }

        try {
            if (is_numeric($value)) {
                $serial = (float) $value;
                if ($serial < 20000 || $serial > 80000) {
                    return [null, 'not a recognised date.'];
                }

                return [Carbon::create(1899, 12, 30)->addDays((int) floor($serial))->format('Y-m-d'), null];
            }

            return [Carbon::parse($value)->format('Y-m-d'), null];
        } catch (Throwable $e) {
            return [null, 'not a recognised date — use YYYY-MM-DD.'];
        }
    }

    protected function authorizeImport()
    {
        $user = auth()->user();
        abort_unless($user && in_array($this->role($user), self::IMPORT_ROLES, true), 403, 'You are not allowed to import vehicles.');

        return $user;
    }

    protected function role($user): string
    {
        return strtoupper(trim((string) $user->account_type));
    }

    protected function scopedUnits($user, string $role)
    {
        $query = Unit::orderBy('unit_name');
        if (in_array($role, ['UNIT ADMINISTRATOR', 'STATION ADMINISTRATOR'], true)) {
            $query->where('id', $user->unit_id);
        }

        return $query->get();
    }

    protected function scopedStations($user, string $role)
    {
        $query = Station::orderBy('station_name');
        if ($role === 'UNIT ADMINISTRATOR') {
            $query->where('unit_id', $user->unit_id);
        } elseif ($role === 'STATION ADMINISTRATOR') {
            $query->where('id', $user->station_id);
        }

        return $query->get();
    }
}
