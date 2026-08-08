<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Database\Seeder;
use RuntimeException;

class WorkUnitSeeder extends Seeder
{
    public function run(): void
    {
        $municipalityId = $this->municipalityId();

        $units = [
            [
                'name' => 'الكهرباء',
                'description' => 'Handles electricity and public lighting complaints.',
                'manager_email' => 'manager@municipality.test',
            ],
            [
                'name' => 'المياه',
                'description' => 'Handles water supply and water leakage complaints.',
                'manager_email' => 'water.manager@municipality.test',
            ],
            [
                'name' => 'الطرق و الحفر',
                'description' => 'Handles roads, asphalt, sidewalks, and pothole complaints.',
                'manager_email' => 'roads.manager@municipality.test',
            ],
            [
                'name' => 'النظافة و القمامة',
                'description' => 'Handles cleanliness, waste collection, and garbage complaints.',
                'manager_email' => 'cleanliness.manager@municipality.test',
            ],
            [
                'name' => 'الصرف الصحي',
                'description' => 'Handles sewer and wastewater complaints.',
                'manager_email' => 'sewer.manager@municipality.test',
            ],
            [
                'name' => 'الحدائق',
                'description' => 'Handles public parks and green area complaints.',
                'manager_email' => 'parks.manager@municipality.test',
            ],
        ];

        foreach ($units as $unitData) {
            $manager = $this->departmentManager($unitData['manager_email'], $municipalityId);

            $workUnitValues = [
                'department_manager_id' => $manager->id,
                'description' => $unitData['description'],
                'is_active' => true,
            ];

            WorkUnit::query()->updateOrCreate([
                'municipality_id' => $municipalityId,
                'name' => $unitData['name'],
            ], $workUnitValues);
        }

        $this->command?->info('Six work units were seeded and linked to their department managers successfully.');
    }

    private function departmentManager(string $email, int $municipalityId): User
    {
        $manager = User::query()
            ->with('employeeProfile')
            ->where('email', $email)
            ->first();

        if ($manager === null) {
            throw new RuntimeException("Department manager [{$email}] was not found. Run DemoUsersSeeder first.");
        }

        if (! $manager->hasRole('department_manager')) {
            throw new RuntimeException("User [{$email}] does not have the department_manager role.");
        }

        if ($manager->employeeProfile === null) {
            throw new RuntimeException("Department manager [{$email}] does not have an employee profile.");
        }

        if ((int) $manager->employeeProfile->municipality_id !== $municipalityId) {
            throw new RuntimeException("Department manager [{$email}] does not belong to the selected municipality.");
        }

        if ($manager->employeeProfile->status !== 'active') {
            throw new RuntimeException("Department manager [{$email}] is not active.");
        }

        return $manager;
    }

    private function municipalityId(): int
    {
        $configuredMunicipalityId = (int) env('SEED_MUNICIPALITY_ID', 0);

        if ($configuredMunicipalityId > 0) {
            $municipalityExists = Municipality::query()->whereKey($configuredMunicipalityId)->exists();

            if (! $municipalityExists) {
                throw new RuntimeException('The municipality configured in SEED_MUNICIPALITY_ID does not exist.');
            }

            return $configuredMunicipalityId;
        }

        $municipalityId = Municipality::query()
            ->where('email', 'kafarsouseh@municipality.test')
            ->value('id');

        if ($municipalityId === null) {
            $municipalityId = Municipality::query()
                ->where('status', true)
                ->orderBy('id')
                ->value('id');
        }

        if ($municipalityId === null) {
            throw new RuntimeException('No municipality was found. Run the municipality seeder first.');
        }

        return (int) $municipalityId;
    }
}
