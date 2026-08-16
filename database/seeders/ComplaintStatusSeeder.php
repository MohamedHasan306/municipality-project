<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComplaintStatusSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $statuses = [
            [
                'key' => 'draft',
                'name' => 'مسودة',
                'is_terminal' => false,
            ],
            [
                'key' => 'submitted',
                'name' => 'تم الإرسال',
                'is_terminal' => false,
            ],
            [
                'key' => 'under_review',
                'name' => 'قيد المراجعة',
                'is_terminal' => false,
            ],
            [
                'key' => 'forwarded_to_department',
                'name' => 'تم التحويل إلى القسم المختص',
                'is_terminal' => false,
            ],
            [
                'key' => 'in_progress',
                'name' => 'قيد التنفيذ',
                'is_terminal' => false,
            ],
            [
                'key' => 'resolved',
                'name' => 'تم الحل',
                'is_terminal' => true,
            ],
            [
                'key' => 'rejected',
                'name' => 'مرفوضة',
                'is_terminal' => true,
            ],
        ];

        $statuses = array_map(
            fn (array $status) => [
                ...$status,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $statuses
        );

        DB::table('complaint_statuses')->upsert(
            $statuses,
            ['key'],
            ['name', 'is_terminal', 'updated_at']
        );
    }
}
