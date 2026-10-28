<?php

namespace Database\Seeders;

use App\Models\ComplaintCategory;
use Illuminate\Database\Seeder;

class ComplaintCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'الطرق والأرصفة',
                'description' => 'الشكاوى المتعلقة بالطرق والأرصفة.',
                'children' => [
                    'حفرة في الطريق',
                    'رصيف متضرر',
                    'إشارة مرور متضررة',
                    'تعدٍ على الطريق',
                ],
            ],
            [
                'name' => 'النظافة',
                'description' => 'الشكاوى المتعلقة بالنظافة والنفايات.',
                'children' => [
                    'تراكم النفايات',
                    'حاوية ممتلئة',
                    'حاوية متضررة',
                    'مكب نفايات عشوائي',
                ],
            ],
            [
                'name' => 'الإنارة',
                'description' => 'الشكاوى المتعلقة بإنارة الشوارع.',
                'children' => [
                    'مصباح شارع معطل',
                    'عمود إنارة متضرر',
                    'انقطاع إنارة شارع',
                ],
            ],
            [
                'name' => 'المياه والصرف الصحي',
                'description' => 'الشكاوى المتعلقة بالمياه والصرف الصحي.',
                'children' => [
                    'تسرب مياه',
                    'انسداد صرف صحي',
                    'فيضان مياه صرف',
                ],
            ],
            [
                'name' => 'الحدائق والمرافق العامة',
                'description' => 'الشكاوى المتعلقة بالحدائق والمرافق العامة.',
                'children' => [
                    'تلف ألعاب الحديقة',
                    'تلف مقاعد عامة',
                    'أشجار بحاجة إلى تقليم',
                ],
            ],
        ];

        foreach ($categories as $parentIndex => $categoryData) {
            $parent = ComplaintCategory::query()->updateOrCreate(
                [
                    'parent_id' => null,
                    'name' => $categoryData['name'],
                ],
                [
                    'description' => $categoryData['description'],
                    'is_active' => true,

                ]
            );

            foreach ($categoryData['children'] as $childIndex => $childName) {
                ComplaintCategory::query()->updateOrCreate(
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                    ],
                    [
                        'description' => null,
                        'is_active' => true,

                    ]
                );
            }
        }
    }
}
