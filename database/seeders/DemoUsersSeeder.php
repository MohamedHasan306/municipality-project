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
        $municipality = Municipality::where('email', 'kafarsouseh@municipality.test')->first();

        if (! $municipality) {
            return;
        }

        $employees = [
            [
                'full_name' => 'Technical Office',
                'email' => 'technical@municipality.test',
                'phone_number' => '0591111111',
                'national_id' => 'EMP-1001',
                'role' => 'technical_office',
            ],
            [
                'full_name' => 'Mayor User',
                'email' => 'mayor@municipality.test',
                'phone_number' => '0592222222',
                'national_id' => 'EMP-1002',
                'role' => 'mayor',
            ],
            [
                'full_name' => 'Engineering Office',
                'email' => 'engineering@municipality.test',
                'phone_number' => '0593333333',
                'national_id' => 'EMP-1003',
                'role' => 'engineering_office',
            ],
            [
                'full_name' => 'Department Manager',
                'email' => 'manager@municipality.test',
                'phone_number' => '0594444444',
                'national_id' => 'EMP-1004',
                'role' => 'department_manager',
            ],
            [
                'full_name' => 'Field Inspector',
                'email' => 'inspector@municipality.test',
                'phone_number' => '0595555555',
                'national_id' => 'EMP-1005',
                'role' => 'field_inspector',
            ],
            [
                'full_name' => 'Municipality Administrator',
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
                    'full_name' => $employeeData['full_name'],
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
                'email' => 'ahmad@citizen.test',
                'phone_number' => '0596666666',
                'full_name' => 'Ahmad Ali',
                'gender' => 'Male',
                'birth_date' => '2000-01-01',
                'place_of_birth' => 'دمشق',
                'national_id' => 'CIT-1001',
                'needs_special_care' => false,
            ],
            [
                'email' => 'sara@citizen.test',
                'phone_number' => '0597777777',
                'full_name' => 'Sara Ahmad',
                'gender' => 'Female',
                'birth_date' => '1999-05-15',
                'place_of_birth' => 'ريف دمشق',
                'national_id' => 'CIT-1002',
                'needs_special_care' => true,
            ],
        ];

        foreach ($citizens as $citizenData) {
            $user = User::firstOrCreate(
                ['email' => $citizenData['email']],
                [
                    'full_name' => $citizenData['full_name'],
                    'phone_number' => $citizenData['phone_number'],
                    'password' => Hash::make('password123'),
                ]
            );

            CitizenProfile::firstOrCreate(
                ['user_id' => $user->id],
                [

                    'municipality_id' => $municipality->id,
                    'gender' => $citizenData['gender'],
                    'birth_date' => $citizenData['birth_date'],
                    'place_of_birth' => $citizenData['place_of_birth'],
                    'national_id' => $citizenData['national_id'],
                    'needs_special_care' => $citizenData['needs_special_care'],
                    'is_verified' => true,
                ]
            );

            $user->assignRole('citizen');
        }
    }
}
