<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Auction extends Model
{
    public const array STATUSES = [
        'draft',
        'scheduled',
        'live',
        'completed',
        'cancelled',
    ];

    use HasFactory, SoftDeletes;

    public const AUCTION_TYPES = ['standard', 'reserve', 'no_reserve'];

    public const BIDDER_ELIGIBILITY = ['all_verified', 'invite_only', 'premium'];

    public const LISTING_VISIBILITY = ['public', 'private', 'unlisted'];

    protected $fillable = [
        'car_id',
        'title',
        'description',
        'start_at',
        'end_at',
        'starting_price',
        'minimum_bid_increment',
        'auction_type',
        'bidder_eligibility',
        'listing_visibility',
        'buyer_premium_percent',
        'seller_commission_percent',
        'payment_deadline_days',
        'reserve_price',
        'participation_fee',
        'status',
        'published',
        'created_by',
        'published_by',
        'published_at',
        'winner_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'starting_price' => 'decimal:2',
        'minimum_bid_increment' => 'decimal:2',
        'reserve_price' => 'decimal:2',
        'participation_fee' => 'decimal:2',
        'buyer_premium_percent' => 'decimal:2',
        'seller_commission_percent' => 'decimal:2',
        'payment_deadline_days' => 'integer',
        'published_at' => 'datetime',
        'published' => 'boolean',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publishedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'winner_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AuctionParticipant::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function result(): HasOne
    {
        return $this->hasOne(AuctionResult::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
