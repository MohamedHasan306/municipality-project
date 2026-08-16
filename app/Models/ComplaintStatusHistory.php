<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintStatusHistory extends Model
{
    protected $fillable = [
        'complaint_report_id',
        'from_status_id',
        'to_status_id',
        'changed_by',
        'note',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'complaint_report_id' => 'integer',
            'from_status_id' => 'integer',
            'to_status_id' => 'integer',
            'changed_by' => 'integer',
            'is_public' => 'boolean',
        ];
    }

    public function complaintReport(): BelongsTo
    {
        return $this->belongsTo(ComplaintReport::class, 'complaint_report_id');
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(ComplaintStatus::class, 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(ComplaintStatus::class, 'to_status_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
