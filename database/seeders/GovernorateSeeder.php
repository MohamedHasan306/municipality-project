<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GovernorateSeeder extends Seeder
{
    public function run(): void
    {
        $governorates = [
            'دمشق',
            'ريف دمشق',
            'حلب',
            'حمص',
            'حماة',
            'اللاذقية',
            'طرطوس',
            'إدلب',
            'الرقة',
            'دير الزور',
            'الحسكة',
            'درعا',
            'السويداء',
            'القنيطرة',
        ];

        foreach ($governorates as $governorate) {
            DB::table('governorates')->updateOrInsert(
                ['name' => $governorate],
                [
                    'name' => $governorate,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
