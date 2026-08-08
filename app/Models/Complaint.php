<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Complaint extends Model
{
    protected $fillable = [
        'municipality_id',
        'category_id',
        'current_status_id',
        'title',
        'canonical_description',
        'text_location',
        'latitude',
        'longitude',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'municipality_id' => 'integer',
            'category_id' => 'integer',
            'current_status_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'submitted_at' => 'datetime',
        ];
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ComplaintCategory::class, 'category_id');
    }

    public function currentStatus(): BelongsTo
    {
        return $this->belongsTo(ComplaintStatus::class, 'current_status_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ComplaintReport::class, 'complaint_id');
    }

    /*
     * This is a read-only convenience relationship.
     *
     * The complaint does not own status histories directly.
     * Each history record belongs to a complaint report.
     */
    public function reportStatusHistories(): HasManyThrough
    {
        return $this->hasManyThrough(
            ComplaintStatusHistory::class,
            ComplaintReport::class,
            'complaint_id',
            'complaint_report_id',
            'id',
            'id'
        );
    }

    public function workUnits(): BelongsToMany
    {
        return $this->belongsToMany(WorkUnit::class, 'complaint_work_unit')
            ->using(ComplaintWorkUnit::class)
            ->withPivot([
                'id',
                'assigned_by',
                'assigned_at',
                'unassigned_at',
            ])
            ->withTimestamps();
    }

    public function activeWorkUnits(): BelongsToMany
    {
        return $this->workUnits()->wherePivotNull('unassigned_at');
    }
}
