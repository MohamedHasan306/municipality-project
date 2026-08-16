<?php

namespace App\Services\ServiceRequests;

use App\Models\ServiceFormField;
use App\Models\ServiceType;
use App\Models\ServiceTypeVersion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use Illuminate\Validation\ValidationException;
use RuntimeException;

class ServiceTypeService
{
    private const SAFE_EXACT_VALIDATION_RULES = [
        'string',
        'numeric',
        'integer',
        'boolean',
        'date',
        'email',
        'url',
        'alpha',
        'alpha_num',
        'alpha_dash',
        'uuid',
    ];

    public function paginate(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        $this->assertSystemAdmin($actor, 'manage service types');

        return ServiceType::query()
            ->with(['municipality', 'activeVersion.fields'])
            ->when(
                isset($filters['municipality_id']),
                fn ($query) => $query->where('municipality_id', $filters['municipality_id'])
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn ($query) => $query->where('is_active', $filters['is_active'])
            )
            ->latest('id')
            ->paginate($perPage);
    }

    public function show(User $actor, ServiceType $serviceType): ServiceType
    {
        $this->assertSystemAdmin($actor, 'manage service types');

        return $serviceType->load(['municipality', 'activeVersion.fields']);
    }

    public function create(User $actor, array $data): ServiceType
    {
        $this->assertSystemAdmin($actor, 'manage service types');
        $this->assertTemplateExists($data['document_template_key']);

        $serviceType = ServiceType::query()->create([
            'municipality_id' => $data['municipality_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'document_template_key' => $data['document_template_key'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $serviceType->load(['municipality', 'activeVersion.fields']);
    }

    public function update(User $actor, ServiceType $serviceType, array $data): ServiceType
    {
        $this->assertSystemAdmin($actor, 'manage service types');

        if (array_key_exists('document_template_key', $data)) {
            $this->assertTemplateExists($data['document_template_key']);
        }

        $serviceType->update($data);

        return $serviceType->fresh(['municipality', 'activeVersion.fields']);
    }

    public function createVersion(User $actor, ServiceType $serviceType, array $fields): ServiceTypeVersion
    {
        $this->assertSystemAdmin($actor, 'manage service forms');

        $normalizedFields = array_map(
            fn (array $field): array => $this->normalizeField($field),
            $fields
        );

        return DB::transaction(function () use ($serviceType, $normalizedFields): ServiceTypeVersion {
            $lockedServiceType = ServiceType::query()
                ->lockForUpdate()
                ->findOrFail($serviceType->id);

            $nextVersionNumber = ((int) $lockedServiceType->versions()->max('version_number')) + 1;

            $lockedServiceType->versions()
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $version = $lockedServiceType->versions()->create([
                'version_number' => $nextVersionNumber,
                'is_active' => true,
            ]);

            $version->fields()->createMany($normalizedFields);

            return $version->load(['serviceType.municipality', 'fields']);
        }, 3);
    }

    private function normalizeField(array $field): array
    {
        if (! in_array($field['field_type'], ServiceFormField::SUPPORTED_TYPES, true)) {
            throw ValidationException::withMessages([
                'fields' => ['An unsupported service form field type was provided.'],
            ]);
        }

        $options = $field['options_json'] ?? null;
        $validationRules = $field['validation_json'] ?? null;

        if (in_array($field['field_type'], [ServiceFormField::SELECT, ServiceFormField::RADIO], true)) {
            if (! is_array($options) || $options === []) {
                throw ValidationException::withMessages([
                    "fields.{$field['field_key']}.options_json" => [
                        'Select and radio fields must contain at least one option.',
                    ],
                ]);
            }

            if (count($options) !== count(array_unique($options, SORT_STRING))) {
                throw ValidationException::withMessages([
                    "fields.{$field['field_key']}.options_json" => [
                        'Select and radio field options must be unique.',
                    ],
                ]);
            }
        } elseif (is_array($options) && $options !== []) {
            throw ValidationException::withMessages([
                "fields.{$field['field_key']}.options_json" => [
                    'Options are allowed only for select and radio fields.',
                ],
            ]);
        }

        if ($field['field_type'] === ServiceFormField::FILE
            && is_array($validationRules)
            && $validationRules !== []) {
            throw ValidationException::withMessages([
                "fields.{$field['field_key']}.validation_json" => [
                    'File validation is managed by the server and must not be supplied here.',
                ],
            ]);
        }

        foreach ($validationRules ?? [] as $rule) {
            $this->assertSafeValidationRule($field['field_type'], $field['field_key'], $rule);
        }

        return [
            'field_key' => $field['field_key'],
            'label' => $field['label'],
            'field_type' => $field['field_type'],
            'is_required' => $field['is_required'],
            'options_json' => $options,
            'validation_json' => $validationRules,
            'condition_json' => $field['condition_json'] ?? null,
            'sort_order' => $field['sort_order'],
        ];
    }

    private function assertSafeValidationRule(string $fieldType, string $fieldKey, string $rule): void
    {
        if ($rule === '' || str_contains($rule, '|') || str_contains($rule, '\\')) {
            $this->throwUnsafeRule($fieldKey, $rule);
        }

        $isSafe = in_array($rule, self::SAFE_EXACT_VALIDATION_RULES, true)
            || $this->isSafeParameterizedRule($rule);

        if (! $isSafe) {
            $this->throwUnsafeRule($fieldKey, $rule);
        }

        $expectedTypeRules = match ($fieldType) {
            ServiceFormField::TEXT,
            ServiceFormField::TEXTAREA,
            ServiceFormField::SELECT,
            ServiceFormField::RADIO => ['string'],
            ServiceFormField::NUMBER => ['numeric', 'integer'],
            ServiceFormField::DATE => ['date'],
            ServiceFormField::CHECKBOX => ['boolean'],
            default => [],
        };

        $baseTypeRules = ['string', 'numeric', 'integer', 'boolean', 'date'];

        if (in_array($rule, $baseTypeRules, true) && ! in_array($rule, $expectedTypeRules, true)) {
            throw ValidationException::withMessages([
                "fields.{$fieldKey}.validation_json" => [
                    "The validation rule [{$rule}] conflicts with the field type [{$fieldType}].",
                ],
            ]);
        }
    }

    private function isSafeParameterizedRule(string $rule): bool
    {
        [$name, $parameters] = array_pad(explode(':', $rule, 2), 2, null);

        if ($parameters === null || $parameters === '') {
            return false;
        }

        return match ($name) {
            'min', 'max', 'size' => preg_match('/^\d+(?:\.\d+)?$/', $parameters) === 1,
            'between' => preg_match('/^\d+(?:\.\d+)?,\d+(?:\.\d+)?$/', $parameters) === 1,
            'decimal' => preg_match('/^\d+(?:,\d+)?$/', $parameters) === 1,
            'digits' => preg_match('/^\d+$/', $parameters) === 1,
            'digits_between' => preg_match('/^\d+,\d+$/', $parameters) === 1,
            'date_format' => preg_match('/^[A-Za-z0-9:\/._ -]+$/', $parameters) === 1,
            'before', 'before_or_equal', 'after', 'after_or_equal' =>
                preg_match('/^[A-Za-z0-9:\/._ +\-]+$/', $parameters) === 1,
            'starts_with', 'ends_with' =>
                preg_match('/^[A-Za-z0-9_., +\-]+$/', $parameters) === 1,
            default => false,
        };
    }

    private function assertTemplateExists(string $templateKey): void
    {
        if ($templateKey !== ServiceDocumentTemplate::KEY) {
            throw ValidationException::withMessages([
                'document_template_key' => [
                    'Only the unified [standard] document template is supported.',
                ],
            ]);
        }
    }

    private function assertSystemAdmin(User $actor, string $permission): void
    {
        abort_unless(
            $actor->hasRole('system_admin') && $actor->can($permission),
            403,
            'Only an authorized system administrator may perform this operation.'
        );
    }

    private function throwUnsafeRule(string $fieldKey, string $rule): never
    {
        throw ValidationException::withMessages([
            "fields.{$fieldKey}.validation_json" => [
                "The validation rule [{$rule}] is not allowed.",
            ],
        ]);
    }
}
