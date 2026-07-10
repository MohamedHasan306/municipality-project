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

    protected $casts = [
        'birth_date' => 'date',
        'is_verified' => 'boolean',
    ];
}
