<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CampuUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('campu_users')->insert([
            'user_id' => 1,
            'campus_id' => 'MAC',
            'created_at' => now(),
        ]);

    }
}
