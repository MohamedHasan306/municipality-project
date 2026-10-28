<?php

namespace Database\Seeders;

use App\Models\ServiceStatus;
use Illuminate\Database\Seeder;

class ServiceStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'code' => ServiceStatus::DRAFT,
                'name' => 'Draft',
                'name_ar' => 'مسودة',
                'is_terminal' => false,
            ],
            [
                'code' => ServiceStatus::SUBMITTED,
                'name' => 'Submitted',
                'name_ar' => 'تم الإرسال',
                'is_terminal' => false,
            ],
            [
                'code' => ServiceStatus::UNDER_REVIEW,
                'name' => 'Under Review',
                'name_ar' => 'قيد المراجعة',
                'is_terminal' => false,
            ],
            [
                'code' => ServiceStatus::PENDING_ENGINEERING_APPROVAL,
                'name' => 'Pending Engineering Approval',
                'name_ar' => 'بانتظار موافقة الجهة الهندسية',
                'is_terminal' => false,
            ],
            [
                'code' => ServiceStatus::PENDING_MAYOR_APPROVAL,
                'name' => 'Pending Mayor Approval',
                'name_ar' => 'بانتظار اعتماد رئيس البلدية',
                'is_terminal' => false,
            ],
            [
                'code' => ServiceStatus::APPROVED_AND_DOCUMENT_ISSUED,
                'name' => 'Approved and Document Issued',
                'name_ar' => 'تمت الموافقة وإصدار الوثيقة',
                'is_terminal' => true,
            ],
            [
                'code' => ServiceStatus::REJECTED,
                'name' => 'Rejected',
                'name_ar' => 'مرفوضة',
                'is_terminal' => true,
            ],
        ];

        foreach ($statuses as $status) {
            ServiceStatus::query()->updateOrCreate(
                ['code' => $status['code']],
                $status
            );
        }
    }
}
