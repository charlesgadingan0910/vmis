<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class AccountTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds the 5 base account types individually (checking each by its
     * `type` string) rather than gating the whole batch behind "is the table
     * empty?". That all-or-nothing guard used to work because nothing else
     * ever wrote to this table before this seeder ran — but
     * 2026_09_22_050000_add_driver_account_type's migration inserts a DRIVER
     * row directly during the migrate phase, which happens before --seed
     * runs. On a `migrate:refresh --seed`, that DRIVER row already makes the
     * table non-empty by the time this seeder runs, so the old guard skipped
     * SUPER ADMINISTRATOR/ADMINISTRATOR/UNIT ADMINISTRATOR/STATION
     * ADMINISTRATOR/VIEWER entirely. Per-type exists() checks make this safe
     * to run no matter what else has already touched the table (a prior
     * migration, or a Super Administrator having manually added one of these
     * via Settings > Account Types).
     */
    public function run(): void
    {
        $baseTypes = [
            ['level' => 1, 'type' => 'SUPER ADMINISTRATOR'],
            ['level' => 2, 'type' => 'ADMINISTRATOR'],
            ['level' => 3, 'type' => 'UNIT ADMINISTRATOR'],
            ['level' => 4, 'type' => 'STATION ADMINISTRATOR'],
            ['level' => 5, 'type' => 'VIEWER'],
        ];

        foreach ($baseTypes as $baseType) {
            if (DB::table('account_types')->where('type', $baseType['type'])->exists()) {
                continue;
            }

            DB::table('account_types')->insert([
                'level'      => $baseType['level'],
                'type'       => $baseType['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
