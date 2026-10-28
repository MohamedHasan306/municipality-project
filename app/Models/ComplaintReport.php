<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComplaintReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'complaint_id',
        'citizen_profile_id',
        'municipality_id',
        'category_id',
        'current_status_id',
        'title',
        'description',
        'text_location',
        'latitude',
        'longitude',
        'submitted_at',
        'linked_by',
        'linked_at',
    ];

    protected function casts(): array
    {
        return [
            'complaint_id' => 'integer',
            'citizen_profile_id' => 'integer',
            'municipality_id' => 'integer',
            'category_id' => 'integer',
            'current_status_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'submitted_at' => 'datetime',
            'linked_at' => 'datetime',
        ];
    }

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    public function citizenProfile(): BelongsTo
    {
        return $this->belongsTo(CitizenProfile::class, 'citizen_profile_id');
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

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ComplaintReportImage::class, 'complaint_report_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ComplaintStatusHistory::class, 'complaint_report_id')
            ->latest('created_at');
    }

    public function belongsToCitizen(User $user): bool
    {
        return $user->citizenProfile !== null
            && (int) $this->citizen_profile_id === (int) $user->citizenProfile->id;
    }

    public function hasStatus(string $statusKey): bool
    {
        if ($this->relationLoaded('currentStatus')) {
            return $this->currentStatus?->key === $statusKey;
        }

        return $this->currentStatus()
            ->where('key', $statusKey)
            ->exists();
    }

    public function isDraft(): bool
    {
        return $this->hasStatus(ComplaintStatus::DRAFT);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isLinked(): bool
    {
        return $this->complaint_id !== null;
    }

    public function canBeModified(): bool
    {
        return $this->deleted_at === null
            && $this->complaint_id === null
            && $this->isDraft();
    }
}
