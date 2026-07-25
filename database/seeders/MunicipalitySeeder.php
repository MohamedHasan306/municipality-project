<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;

class MunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        Municipality::firstOrCreate(
            ['email' => 'kafarsouseh@municipality.test'],
            [
                'governorate_id' => 1,
                'name' => 'بلدية كفرسوسة',
                'address' => 'دمشق_كفرسوسة',
                'phone' => '092000000',
                'status' => true,
            ]
        );


    }
}
