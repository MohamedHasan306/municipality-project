<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceType extends Model
{
    protected $fillable = [
        'municipality_id',
        'name',
        'description',
        'document_template_key',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'municipality_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ServiceTypeVersion::class)
            ->orderBy('version_number');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(ServiceTypeVersion::class)
            ->where('is_active', true);
    }
}
