<?php

namespace Database\Seeders;

use App\Models\Municipality;
use App\Models\ServiceFormField;
use App\Models\ServiceType;
use App\Models\ServiceTypeVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceTypeSeeder extends Seeder
{
    /**
     * Temporary document template.
     *
     * Currently the project has the building_permit Blade template.
     * Later, after creating the unified/default document template,
     * this value can be changed to:
     *
     * default
     */
    private const DOCUMENT_TEMPLATE_KEY = 'building_permit';

    private const MUNICIPALITY_EMAIL = 'kafarsouseh@municipality.test';

    public function run(): void
    {
        $municipality = Municipality::query()
            ->where('email', self::MUNICIPALITY_EMAIL)
            ->first();

        if ($municipality === null) {
            throw new RuntimeException(
                'The Kafarsouseh municipality was not found. Run MunicipalitySeeder first.'
            );
        }

        $services = [
            [
                'name' => 'إذن حفر بئر ماء',
                'description' => 'طلب الحصول على إذن لحفر بئر ماء ضمن نطاق البلدية.',
                'document_template_key' => self::DOCUMENT_TEMPLATE_KEY,

                'fields' => [
                    [
                        'field_key' => 'well_address',
                        'label' => 'عنوان البئر',
                        'field_type' => ServiceFormField::TEXT,
                        'is_required' => true,
                        'options_json' => null,
                        'validation_json' => [
                            'string',
                            'max:255',
                        ],
                        'condition_json' => null,
                        'sort_order' => 1,
                    ],

                    [
                        'field_key' => 'well_depth',
                        'label' => 'عمق البئر (متر)',
                        'field_type' => ServiceFormField::NUMBER,
                        'is_required' => true,
                        'options_json' => null,
                        'validation_json' => [
                            'numeric',
                            'min:1',
                        ],
                        'condition_json' => null,
                        'sort_order' => 2,
                    ],

                    [
                        'field_key' => 'environmental_resources_permit',
                        'label' => 'إذن موارد البيئة',
                        'field_type' => ServiceFormField::FILE,
                        'is_required' => true,
                        'options_json' => null,
                        'validation_json' => null,
                        'condition_json' => null,
                        'sort_order' => 4,
                    ]
                ],
            ],

            [
                'name' => 'ترخيص بناء',
                'description' => 'طلب الحصول على ترخيص بناء ضمن نطاق البلدية.',
                'document_template_key' => self::DOCUMENT_TEMPLATE_KEY,

                'fields' => [
                    [
                        'field_key' => 'property_area',
                        'label' => 'مساحة العقار (متر مربع)',
                        'field_type' => ServiceFormField::NUMBER,
                        'is_required' => true,
                        'options_json' => null,
                        'validation_json' => [
                            'numeric',
                            'min:1',
                        ],
                        'condition_json' => null,
                        'sort_order' => 1,
                    ],

                    [
                        'field_key' => 'building_category',
                        'label' => 'نوع البناء',
                        'field_type' => ServiceFormField::SELECT,
                        'is_required' => true,
                        'options_json' => [
                            'residential',
                            'commercial',
                        ],
                        'validation_json' => [
                            'string',
                        ],
                        'condition_json' => null,
                        'sort_order' => 2,
                    ],

                    [
                        'field_key' => 'property_plan',
                        'label' => 'مخطط البناء',
                        'field_type' => ServiceFormField::FILE,
                        'is_required' => true,
                        'options_json' => null,
                        'validation_json' => null,
                        'condition_json' => null,
                        'sort_order' => 4,
                    ]

                ],
            ],

            [
                'name' => 'إذن حفر مجاري',
                'description' => 'طلب الحصول على إذن لتنفيذ أعمال حفر مجاري ضمن نطاق البلدية.',
                'document_template_key' => self::DOCUMENT_TEMPLATE_KEY,

                'fields' => [
                    [
                        'field_key' => 'excavation_address',
                        'label' => 'عنوان الحفر',
                        'field_type' => ServiceFormField::TEXT,
                        'is_required' => true,
                        'options_json' => null,
                        'validation_json' => [
                            'string',
                            'max:255',
                        ],
                        'condition_json' => null,
                        'sort_order' => 1,
                    ],

                    [
                        'field_key' => 'excavation_length',
                        'label' => 'طول الحفرة (متر)',
                        'field_type' => ServiceFormField::NUMBER,
                        'is_required' => true,
                        'options_json' => null,
                        'validation_json' => [
                            'numeric',
                            'min:1',
                        ],
                        'condition_json' => null,
                        'sort_order' => 2,
                    ],

                    [
                        'field_key' => 'excavation_width',
                        'label' => 'عرض الحفرة (متر)',
                        'field_type' => ServiceFormField::NUMBER,
                        'is_required' => true,
                        'options_json' => null,
                        'validation_json' => [
                            'numeric',
                            'min:0.1',
                        ],
                        'condition_json' => null,
                        'sort_order' => 3,
                    ],
                ],
            ],
        ];

        foreach ($services as $serviceData) {
            $this->seedService(
                $municipality->id,
                $serviceData
            );
        }

        $this->command?->info(
            'Three municipal service types and their dynamic fields were seeded successfully.'
        );
    }

    /**
     * Create or update one service and its active version.
     */
    private function seedService(
        int $municipalityId,
        array $serviceData
    ): void {
        DB::transaction(function () use (
            $municipalityId,
            $serviceData
        ): void {
            /*
             * Create or update service type.
             */
            $serviceType = ServiceType::query()->updateOrCreate(
                [
                    'municipality_id' => $municipalityId,
                    'name' => $serviceData['name'],
                ],
                [
                    'description' => $serviceData['description'],
                    'document_template_key' => $serviceData['document_template_key'],
                    'is_active' => true,
                ]
            );

            /*
             * Disable all active versions first.
             */
            $serviceType->versions()
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                ]);

            /*
             * Initial version.
             */
            $version = ServiceTypeVersion::query()->updateOrCreate(
                [
                    'service_type_id' => $serviceType->id,
                    'version_number' => 1,
                ],
                [
                    'is_active' => true,
                ]
            );

            /*
             * Create or update fields.
             */
            $fieldKeys = [];

            foreach ($serviceData['fields'] as $fieldData) {
                $fieldKeys[] = $fieldData['field_key'];

                ServiceFormField::query()->updateOrCreate(
                    [
                        'service_type_version_id' => $version->id,
                        'field_key' => $fieldData['field_key'],
                    ],
                    [
                        'label' => $fieldData['label'],
                        'field_type' => $fieldData['field_type'],
                        'is_required' => $fieldData['is_required'],
                        'options_json' => $fieldData['options_json'],
                        'validation_json' => $fieldData['validation_json'],
                        'condition_json' => $fieldData['condition_json'],
                        'sort_order' => $fieldData['sort_order'],
                    ]
                );
            }

            /*
             * Remove fields that no longer exist in the definition.
             */
            $version->fields()
                ->whereNotIn('field_key', $fieldKeys)
                ->delete();
        });
    }
}
