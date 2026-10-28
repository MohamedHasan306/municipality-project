<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceStatus extends Model
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const UNDER_REVIEW = 'under_review';

    public const PENDING_ENGINEERING_APPROVAL = 'pending_engineering_approval';

    public const PENDING_MAYOR_APPROVAL = 'pending_mayor_approval';

    public const APPROVED_AND_DOCUMENT_ISSUED = 'approved_and_document_issued';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'is_terminal' => 'boolean',
        ];
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class, 'current_status_id');
    }

    public function historiesFrom(): HasMany
    {
        return $this->hasMany(ServiceStatusHistory::class, 'from_status_id');
    }

    public function historiesTo(): HasMany
    {
        return $this->hasMany(ServiceStatusHistory::class, 'to_status_id');
    }
}
