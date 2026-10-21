<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    public const array STATUSES = [
        'draft',
        'under_review',
        'available',
        'inspection_scheduled',
        'auction_scheduled',
        'live_auction',
        'sold',
        'unsold',
        'removed',
        'archived',
    ];

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'location_id',
        'category_id',
        'make_id',
        'stock_inventory_id',
        'make',
        'model',
        'variant',
        'manufacturing_year',
        'registration_year',
        'registration_date',
        'fuel_type',
        'transmission',
        'engine_capacity_cc',
        'seating_capacity',
        'body_type',
        'color',
        'kilometer',
        'number_of_owners',
        'registration_number',
        'vin',
        'engine_number',
        'expected_price',
        'reserve_price',
        'starting_auction_price',
        'status',
        'assigned_manager_id',
        'short_description',
        'detailed_description',
        'specifications',
        'primary_image_path',
        'walkaround_video_path',
        'submitted_for_review_at',
        'documents_approved_at',
        'documents_approved_by',
        'approved_at',
        'approved_by',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'kilometer' => 'decimal:2',
            'expected_price' => 'decimal:2',
            'reserve_price' => 'decimal:2',
            'starting_auction_price' => 'decimal:2',
            'registration_date' => 'date',
            'engine_capacity_cc' => 'integer',
            'seating_capacity' => 'integer',
            'submitted_for_review_at' => 'datetime',
            'documents_approved_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'category_id');
    }

    public function makeRef(): BelongsTo
    {
        return $this->belongsTo(Make::class, 'make_id');
    }

    public function seller(): HasOne
    {
        return $this->hasOne(CarSeller::class);
    }

    public function assignedManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_manager_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function documentsApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'documents_approved_by');
    }

    public function isApproved(): bool
    {
        // Legacy cars that predate the approval workflow are grandfathered:
        // an "available" car counts as approved even if approved_at is null.
        return $this->status === 'available';
    }

    public function images(): HasMany
    {
        return $this->hasMany(CarImage::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CarDocument::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function auctions(): HasMany
    {
        return $this->hasMany(Auction::class);
    }
}
