<?php

namespace App\Models;

use App\Models\ComplaintReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CitizenProfile extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    protected $casts = [
        'birth_date' => 'date',
        'needs_special_care' => 'boolean',
        'is_verified' => 'boolean',
    ];

    public function complaintReports(): HasMany
    {
        return $this->hasMany(
            ComplaintReport::class,
            'citizen_profile_id'
        );
    }
}
