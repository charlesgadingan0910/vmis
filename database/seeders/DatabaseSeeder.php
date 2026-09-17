<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AccountTypesTableSeeder::class);
        $this->call(RanksTableSeeder::class);
        $this->call(UnitsTableSeeder::class);
        $this->call(StationsTableSeeder::class);
        $this->call(UsersTableSeeder::class);
        $this->call(VehicleTypesTableSeeder::class);
    }
}
