<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceStatusHistory extends Model
{
    protected $fillable = [
        'service_request_id',
        'from_status_id',
        'to_status_id',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'service_request_id' => 'integer',
            'from_status_id' => 'integer',
            'to_status_id' => 'integer',
            'changed_by' => 'integer',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(ServiceStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(ServiceStatus::class, 'to_status_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
