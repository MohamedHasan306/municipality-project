<?php

namespace App\Models;

use App\Models\Complaint;
use App\Models\ComplaintReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ComplaintCategory extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            ComplaintCategory::class,
            'parent_id'
        );
    }

    public function children(): HasMany
    {
        return $this->hasMany(
            ComplaintCategory::class,
            'parent_id'
        )->orderBy('sort_order');
    }

    public function complaints()
    {
        return $this->hasMany(
            Complaint::class,
            'category_id'
        );
    }

    public function reports()
    {
        return $this->hasMany(
            ComplaintReport::class,
            'category_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
