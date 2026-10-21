<?php

namespace App\Models;

use Database\Factories\BidFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bid extends Model
{
    /** @use HasFactory<BidFactory> */
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'customer_id',
        'bid_amount',
        'bid_sequence',
        'status',
        'device_metadata',
    ];

    protected function casts(): array
    {
        return [
            'bid_amount' => 'decimal:2',
            'device_metadata' => 'array',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
