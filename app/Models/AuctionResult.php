<?php

namespace App\Models;

use Database\Factories\AuctionResultFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionResult extends Model
{
    /** @use HasFactory<AuctionResultFactory> */
    use HasFactory;

    protected $fillable = [
        'auction_id',
        'winner_id',
        'winning_bid_id',
        'status',
        'finalized_by',
        'finalized_at',
        'settlement_status',
    ];

    protected function casts(): array
    {
        return [
            'finalized_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'winner_id');
    }

    public function winningBid(): BelongsTo
    {
        return $this->belongsTo(Bid::class, 'winning_bid_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
