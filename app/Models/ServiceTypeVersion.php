<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceTypeVersion extends Model
{
    protected $fillable = [
        'service_type_id',
        'version_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'service_type_id' => 'integer',
            'version_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ServiceFormField::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }
}
