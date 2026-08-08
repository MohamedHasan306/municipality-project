<?php

namespace Database\Seeders;

use App\Models\CitizenProfile;
use App\Models\EmployeeProfile;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $municipality = Municipality::query()
            ->where('email', 'kafarsouseh@municipality.test')
            ->first();

        if ($municipality === null) {
            throw new RuntimeException('The Kafarsouseh municipality was not found. Run the municipality seeder first.');
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

            /*
             * Department managers.
             */
            [
                'full_name' => 'Electricity Department Manager',
                'email' => 'manager@municipality.test',
                'phone_number' => '0594444401',
                'national_id' => 'EMP-1004',
                'role' => 'department_manager',
            ],
            [
                'full_name' => 'Water Department Manager',
                'email' => 'water.manager@municipality.test',
                'phone_number' => '0594444402',
                'national_id' => 'EMP-1101',
                'role' => 'department_manager',
            ],
            [
                'full_name' => 'Roads Department Manager',
                'email' => 'roads.manager@municipality.test',
                'phone_number' => '0594444403',
                'national_id' => 'EMP-1102',
                'role' => 'department_manager',
            ],
            [
                'full_name' => 'Cleanliness Department Manager',
                'email' => 'cleanliness.manager@municipality.test',
                'phone_number' => '0594444404',
                'national_id' => 'EMP-1103',
                'role' => 'department_manager',
            ],
            [
                'full_name' => 'Sewer Department Manager',
                'email' => 'sewer.manager@municipality.test',
                'phone_number' => '0594444405',
                'national_id' => 'EMP-1104',
                'role' => 'department_manager',
            ],
            [
                'full_name' => 'Parks Department Manager',
                'email' => 'parks.manager@municipality.test',
                'phone_number' => '0594444406',
                'national_id' => 'EMP-1105',
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
                'phone_number' => '0595555556',
                'national_id' => 'EMP-1006',
                'role' => 'municipality_admin',
            ],
        ];

        foreach ($employees as $employeeData) {
            $userValues = [
                'full_name' => $employeeData['full_name'],
                'phone_number' => $employeeData['phone_number'],
                'password' => Hash::make('password123'),
                'must_change_password' => false,
            ];

            $user = User::query()->updateOrCreate(['email' => $employeeData['email']], $userValues);

            $profileValues = [
                'municipality_id' => $municipality->id,
                'hire_date' => now()->toDateString(),
                'national_id' => $employeeData['national_id'],
                'status' => 'active',
            ];

            EmployeeProfile::query()->updateOrCreate(['user_id' => $user->id], $profileValues);

            $user->syncRoles([$employeeData['role']]);
        }

        $citizens = [
            [
                'email' => 'ahmad@citizen.test',
                'phone_number' => '0596666666',
                'full_name' => 'Ahmad Ali',
                'gender' => 'Male',
                'birth_date' => '2000-01-01',
                'place_of_birth' => 'Damascus',
                'national_id' => 'CIT-1001',
                'needs_special_care' => false,
            ],
            [
                'email' => 'sara@citizen.test',
                'phone_number' => '0597777777',
                'full_name' => 'Sara Ahmad',
                'gender' => 'Female',
                'birth_date' => '1999-05-15',
                'place_of_birth' => 'Rural Damascus',
                'national_id' => 'CIT-1002',
                'needs_special_care' => true,
            ],
        ];

        foreach ($citizens as $citizenData) {
            $userValues = [
                'full_name' => $citizenData['full_name'],
                'phone_number' => $citizenData['phone_number'],
                'password' => Hash::make('password123'),
                'must_change_password' => false,
            ];

            $user = User::query()->updateOrCreate(['email' => $citizenData['email']], $userValues);

            $profileValues = [
                'municipality_id' => $municipality->id,
                'gender' => $citizenData['gender'],
                'birth_date' => $citizenData['birth_date'],
                'place_of_birth' => $citizenData['place_of_birth'],
                'national_id' => $citizenData['national_id'],
                'needs_special_care' => $citizenData['needs_special_care'],
                'is_verified' => true,
            ];

            CitizenProfile::query()->updateOrCreate(['user_id' => $user->id], $profileValues);

            $user->syncRoles(['citizen']);
        }

        $this->command?->info('Demo users and six department managers were seeded successfully.');
    }
}
