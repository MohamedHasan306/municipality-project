<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeProfile extends Model
{
    protected $fillable = [
        'user_id',
        'municipality_id',
        'hire_date',
        'national_id',
        'status'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function municipality(){
        return $this->belongsTo(Municipality::class);
    }
}
