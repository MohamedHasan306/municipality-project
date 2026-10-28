<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@municipality.test'],
            [
                'full_name' => 'Admin',
                'phone_number' => '0590000000',
                'password' => Hash::make('password123'),
            ]
        );

        $admin->assignRole('system_admin');
    }
}
