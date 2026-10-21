<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const array STATUSES = ['pending', 'captured', 'failed', 'released', 'refunded'];

    public const array TYPES = ['entry_fee', 'winning_bid'];

    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'auction_id',
        'amount',
        'currency',
        'gateway',
        'gateway_reference_id',
        'status',
        'payment_type',
        'successful_at',
        'captured_at',
        'released_at',
        'refunded_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'successful_at' => 'datetime',
            'captured_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }
}
