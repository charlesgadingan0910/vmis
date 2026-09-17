<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->updateOrInsert(
            ['badge_number' => "superadmin001"],
            [
                'account_type' => "SUPER ADMINISTRATOR",
                'rank' => 'Pat',
                'lastname' => 'Administrator',
                'firstname' => 'Super',
                'fullname' => 'Super Administrator',
                'email' => 'super.admin@pnp.gov.ph',
                'password' => bcrypt('P@ssw0rd12345'),
            ]
        );
    }
}
