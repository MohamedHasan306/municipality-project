<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignature extends Model
{
    protected $fillable = [
        'generated_document_id',
        'signature_value',
        'signed_document_hash',
        'algorithm',
        'signed_by',
        'signed_at',
        'certificate_serial',
    ];

    protected function casts(): array
    {
        return [
            'generated_document_id' => 'integer',
            'signed_by' => 'integer',
            'signed_at' => 'datetime',
        ];
    }

    public function generatedDocument(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
