<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    protected $guarded = [];

    public function employee(): HasMany
    {
        return $this->hasMany(EmployeeProfile::class);
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function citizenProfiles(): HasMany
    {
        return $this->hasMany(CitizenProfile::class);
    }

    public function workUnits(): HasMany
    {
        return $this->hasMany(WorkUnit::class);
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class);
    }

    public function complaintReports(): HasMany
    {
        return $this->hasMany(ComplaintReport::class);
    }

    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }
}
