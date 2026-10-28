<?php

namespace App\Models;

use App\Models\ComplaintReport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplaintReportImage extends Model
{
    protected $fillable = [
        'complaint_report_id',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(
            ComplaintReport::class,
            'complaint_report_id'
        );
    }
}
