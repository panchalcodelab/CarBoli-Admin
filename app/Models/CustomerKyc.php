<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class CustomerKyc extends Model
{
    use HasFactory;

    protected $table = 'customer_kyc';

    protected $fillable = [
        'customer_id',
        'pan',
        'identity_document_type',
        'identity_document_number',
        'address_proof',
        'document_path',
        'selfie_path',
        'status',
        'rejection_reason',
        'reviewer_id',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'entity', 'entity_type', 'entity_id');
    }
}
