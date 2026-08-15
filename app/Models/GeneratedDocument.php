<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GeneratedDocument extends Model
{
    protected $fillable = [
        'service_request_id',
        'document_number',
        'file_path',
        'document_hash',
        'verification_code',
        'generated_by',
        'issued_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'service_request_id' => 'integer',
            'generated_by' => 'integer',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function signature(): HasOne
    {
        return $this->hasOne(DocumentSignature::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? true;
    }
}
