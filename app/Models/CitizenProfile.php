<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
