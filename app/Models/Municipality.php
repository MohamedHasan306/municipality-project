<?php

namespace App\Models;

use App\Models\CitizenProfile;
use App\Models\Complaint;
use App\Models\ComplaintReport;
use App\Models\EmployeeProfile;
use App\Models\Governorate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{

     protected $guarded = [];

    public function employee(){
        return $this->hasmany(EmployeeProfile::class);
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function citizenProfiles()
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
}
