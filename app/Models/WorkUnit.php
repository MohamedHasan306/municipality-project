<?php

namespace App\Models;


use App\Models\Complaint;
use App\Models\ComplaintWorkUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkUnit extends Model
{
   // use HasFactory;

    protected $fillable = [
        'municipality_id',
        'department_manager_id',
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function complaints(): BelongsToMany
    {
        return $this->belongsToMany(
            Complaint::class,
            'complaint_work_unit'
        )
            ->using(ComplaintWorkUnit::class)
            ->withPivot([
                'id',
                'assigned_by',
                'assigned_at',
                'unassigned_at',
            ])
            ->withTimestamps();
    }



    public function departmentManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'department_manager_id');
    }


}
