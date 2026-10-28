<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComplaintStatus extends Model
{
    public const DRAFT = 'draft';
    public const SUBMITTED = 'submitted';
    public const UNDER_REVIEW = 'under_review';
    public const FORWARDED_TO_DEPARTMENT = 'forwarded_to_department';
    public const IN_PROGRESS = 'in_progress';
    public const RESOLVED = 'resolved';
    public const REJECTED = 'rejected';

    protected $fillable = [
        'key',
        'name',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
        ];
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'current_status_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ComplaintReport::class, 'current_status_id');
    }

    public function historiesFromThisStatus(): HasMany
    {
        return $this->hasMany(ComplaintStatusHistory::class, 'from_status_id');
    }

    public function historiesToThisStatus(): HasMany
    {
        return $this->hasMany(ComplaintStatusHistory::class, 'to_status_id');
    }
}
