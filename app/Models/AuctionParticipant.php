<?php

namespace App\Models;

use Database\Factories\AuctionParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionParticipant extends Model
{
    /** @use HasFactory<AuctionParticipantFactory> */
    use HasFactory;

    public const array ELIGIBILITY_STATUSES = ['pending', 'eligible', 'ineligible'];

    protected $fillable = [
        'auction_id',
        'customer_id',
        'registration_status',
        'entry_fee_amount',
        'payment_status',
        'kyc_status',
        'participation_date',
        'eligibility_status',
    ];

    protected function casts(): array
    {
        return [
            'entry_fee_amount' => 'decimal:2',
            'participation_date' => 'datetime',
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
