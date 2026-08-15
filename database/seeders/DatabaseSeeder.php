<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            GovernorateSeeder::class,
            MunicipalitySeeder::class,

            RolePermissionSeeder::class,

            ComplaintStatusSeeder::class,
            ServiceStatusSeeder::class,
            ComplaintCategorySeeder::class,

            DemoUsersSeeder::class,
            WorkUnitSeeder::class,
            AdminUserSeeder::class,

            ComplaintDemoSeeder::class,
        ]);
    }
}
