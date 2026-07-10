<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipality extends Model
{

     protected $guarded = [];

    public function employee(){
        return $this->hasmany(EmployeeProfile::class);
    }
}
