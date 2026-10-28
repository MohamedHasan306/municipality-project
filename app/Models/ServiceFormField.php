<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFormField extends Model
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const NUMBER = 'number';

    public const DATE = 'date';

    public const SELECT = 'select';

    public const RADIO = 'radio';

    public const CHECKBOX = 'checkbox';

    public const FILE = 'file';

    public const SUPPORTED_TYPES = [
        self::TEXT,
        self::TEXTAREA,
        self::NUMBER,
        self::DATE,
        self::SELECT,
        self::RADIO,
        self::CHECKBOX,
        self::FILE,
    ];

    protected $fillable = [
        'service_type_version_id',
        'field_key',
        'label',
        'field_type',
        'is_required',
        'options_json',
        'validation_json',
        'condition_json',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'service_type_version_id' => 'integer',
            'is_required' => 'boolean',
            'options_json' => 'array',
            'validation_json' => 'array',
            'condition_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function serviceTypeVersion(): BelongsTo
    {
        return $this->belongsTo(ServiceTypeVersion::class);
    }
}
