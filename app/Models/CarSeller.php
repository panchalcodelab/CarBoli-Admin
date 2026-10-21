<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarSeller extends Model
{
    public const SELLER_TYPES = ['dealer', 'individual'];

    protected $fillable = [
        'car_id',
        'customer_id',
        'seller_type',
        'name',
        'mobile',
        'email',
        'external_reference',
        'address',
        'city',
        'state',
        'pincode',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
