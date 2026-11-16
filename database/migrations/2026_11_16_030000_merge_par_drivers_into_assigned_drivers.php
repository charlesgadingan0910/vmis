<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Primary / Alternate PAR (Property Acknowledgement Receipt) drivers ARE the
 * vehicle's Assigned Driver 1 / 2 — there is now one set of fields, not two.
 *
 * Any vehicle that had a separate par_driver_id / par_driver_id_2 chosen gets that
 * driver copied into the matching assigned-driver slot when the slot is empty (with
 * a driver_assignments history row, like any other assignment). Slots that already
 * hold a different assigned driver are left as they are.
 *
 * The par_driver_id / par_driver_id_2 columns stay in the table, now unused, so this
 * migration never destroys data; a later clean-up migration can drop them.
 * par_driver_name / par_driver_name_2 remain in use: they hold the typed name of a
 * PAR holder who is not yet a driver, until it is linked in Driver Management.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['par_driver_id' => 'assigned_driver_id', 'par_driver_id_2' => 'assigned_driver_id_2'] as $parColumn => $assignedColumn) {
            $vehicles = DB::table('vehicles')
                ->whereNotNull($parColumn)
                ->whereNull($assignedColumn)
                ->get(['id', $parColumn]);

            foreach ($vehicles as $vehicle) {
                DB::table('vehicles')->where('id', $vehicle->id)->update([
                    $assignedColumn => $vehicle->{$parColumn},
                    'updated_at'    => now(),
                ]);

                DB::table('driver_assignments')->insert([
                    'vehicle_id'  => $vehicle->id,
                    'driver_id'   => $vehicle->{$parColumn},
                    'assigned_at' => now(),
                    'notes'       => 'Carried over from the former separate PAR driver field.',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data copy only — nothing to undo.
    }
};
