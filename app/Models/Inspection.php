<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inspection extends Model
{
    public const array STATUSES = [
        'requested',
        'scheduled',
        'assigned',
        'confirmed',
        'in_progress',
        'completed',
        'cancelled',
        'no_show',
    ];

    use HasFactory;

    public const CONDITION_RATINGS = ['excellent', 'good', 'fair', 'poor'];

    public const ACCIDENT_HISTORY = ['none', 'minor', 'major'];

    public const FLOOD_DAMAGE = ['no', 'yes'];

    protected $fillable = [
        'car_id',
        'customer_id',
        'assigned_staff_id',
        'inspection_date',
        'start_time',
        'end_time',
        'location',
        'status',
        'exterior_condition',
        'interior_condition',
        'engine_condition',
        'transmission_condition',
        'tyre_condition',
        'battery_condition',
        'accident_history',
        'flood_damage',
        'inspection_score',
        'inspector_remarks',
        'notes',
        'inspection_report_path',
    ];

    protected $casts = [
        'inspection_date' => 'date',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(InspectionDocument::class);
    }
}
