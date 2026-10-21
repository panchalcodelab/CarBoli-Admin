<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $table = 'notifications';

    public const array STATUSES = ['queued', 'sent', 'failed'];

    protected $fillable = [
        'customer_id',
        'type',
        'channel',
        'event',
        'template_identifier',
        'status',
        'provider_reference',
        'sent_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
