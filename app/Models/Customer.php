<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    public const array ACCOUNT_STATUSES = ['active', 'inactive', 'blocked', 'suspended'];

    public const array KYC_STATUSES = ['pending', 'under_review', 'approved', 'rejected'];

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'full_name',
        'email',
        'mobile_number',
        'account_status',
        'kyc_status',
        'kyc_submitted_at',
        'kyc_approved_at',
        'kyc_rejection_reason',
        'profile_information',
        'address',
        'city',
        'state',
        'pincode',
        'country',
        'last_login_at',
        'is_blocked',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
        'kyc_submitted_at' => 'datetime',
        'kyc_approved_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function kyc(): HasMany
    {
        return $this->hasMany(CustomerKyc::class);
    }

    public function auctionParticipations(): HasMany
    {
        return $this->hasMany(AuctionParticipant::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function auctionResults(): HasMany
    {
        return $this->hasMany(AuctionResult::class, 'winner_id');
    }
}
