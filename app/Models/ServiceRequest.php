<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceRequest extends Model
{
    protected $fillable = [
        'citizen_profile_id',
        'service_type_version_id',
        'current_status_id',
        'data_json',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'citizen_profile_id' => 'integer',
            'service_type_version_id' => 'integer',
            'current_status_id' => 'integer',
            'data_json' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function citizenProfile(): BelongsTo
    {
        return $this->belongsTo(CitizenProfile::class);
    }

    public function serviceTypeVersion(): BelongsTo
    {
        return $this->belongsTo(ServiceTypeVersion::class);
    }

    public function currentStatus(): BelongsTo
    {
        return $this->belongsTo(ServiceStatus::class, 'current_status_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ServiceStatusHistory::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceRequestAttachment::class);
    }

    public function generatedDocument(): HasOne
    {
        return $this->hasOne(GeneratedDocument::class);
    }
}
