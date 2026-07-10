<?php

namespace Database\Seeders;

use App\Models\CitizenProfile;
use App\Models\EmployeeProfile;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $municipality = Municipality::where('email', 'nablus@municipality.test')->first();

        if (! $municipality) {
            return;
        }

        $employees = [
            [
                'name' => 'Technical Office',
                'email' => 'technical@municipality.test',
                'phone_number' => '0591111111',
                'national_id' => 'EMP-1001',
                'role' => 'technical_office',
            ],
            [
                'name' => 'Mayor User',
                'email' => 'mayor@municipality.test',
                'phone_number' => '0592222222',
                'national_id' => 'EMP-1002',
                'role' => 'mayor',
            ],
            [
                'name' => 'Engineering Office',
                'email' => 'engineering@municipality.test',
                'phone_number' => '0593333333',
                'national_id' => 'EMP-1003',
                'role' => 'engineering_office',
            ],
            [
                'name' => 'Department Manager',
                'email' => 'manager@municipality.test',
                'phone_number' => '0594444444',
                'national_id' => 'EMP-1004',
                'role' => 'department_manager',
            ],
            [
                'name' => 'Field Inspector',
                'email' => 'inspector@municipality.test',
                'phone_number' => '0595555555',
                'national_id' => 'EMP-1005',
                'role' => 'field_inspector',
            ],
            [
                'name' => 'Municipality Administrator',
                'email' => 'municipality_admin@municipality.test',
                'phone_number' => '0595555555',
                'national_id' => 'EMP-1006',
                'role' => 'municipality_admin',
            ],
        ];

        foreach ($employees as $employeeData) {
            $user = User::firstOrCreate(
                ['email' => $employeeData['email']],
                [
                    'name' => $employeeData['name'],
                    'phone_number' => $employeeData['phone_number'],
                    'password' => Hash::make('password123'),
                ]
            );

            EmployeeProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'municipality_id' => $municipality->id,
                    'hire_date' => now()->toDateString(),
                    'national_id' => $employeeData['national_id'],
                    'status' => 'active',
                ]
            );

            $user->assignRole($employeeData['role']);
        }

        $citizens = [
            [
                'name' => 'Ahmad Citizen',
                'email' => 'ahmad@citizen.test',
                'phone_number' => '0596666666',
                'full_name' => 'Ahmad Ali',
                'gender' => 'Male',
                'birth_date' => '2000-01-01',
                'national_id' => 'CIT-1001',
                'address' => 'Nablus',
            ],
            [
                'name' => 'Sara Citizen',
                'email' => 'sara@citizen.test',
                'phone_number' => '0597777777',
                'full_name' => 'Sara Ahmad',
                'gender' => 'Female',
                'birth_date' => '1999-05-15',
                'national_id' => 'CIT-1002',
                'address' => 'Nablus',
            ],
        ];

        foreach ($citizens as $citizenData) {
            $user = User::firstOrCreate(
                ['email' => $citizenData['email']],
                [
                    'name' => $citizenData['name'],
                    'phone_number' => $citizenData['phone_number'],
                    'password' => Hash::make('password123'),
                ]
            );

            CitizenProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => $citizenData['full_name'],
                    'gender' => $citizenData['gender'],
                    'birth_date' => $citizenData['birth_date'],
                    'national_id' => $citizenData['national_id'],
                    'address' => $citizenData['address'],
                    'is_verified' => true,
                ]
            );

            $user->assignRole('citizen');
        }
    }
}
