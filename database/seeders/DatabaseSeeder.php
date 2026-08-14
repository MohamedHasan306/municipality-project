<?php

namespace Database\Seeders;

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
        // User::factory(10)->create();


        $this->call([
            GovernorateSeeder::class,
            MunicipalitySeeder::class,

            RolePermissionSeeder::class,

            ComplaintStatusSeeder::class,
            ComplaintCategorySeeder::class,
            DemoUsersSeeder::class,
            WorkUnitSeeder::class,


            AdminUserSeeder::class,

            ComplaintDemoSeeder::class,

            DemoUsersSeeder::class,

        ]);
    }
}
