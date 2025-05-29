<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CarRequest extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'general_user_id',
        'pickup_location',
        'dropoff_location',
        'status',
        'assigned_at',
        'driver_id',
        'no_of_passengers',
        'pickup_latitude',
        'pickup_longitude',
        'dropoff_latitude',
        'dropoff_longitude',
        'requested_at',
    ];

    // Relationships
    public function generalUser()
    {
        return $this->belongsTo(GeneralUser::class);
    }

    public function trip()
    {
        return $this->hasOne(Trip::class, 'request_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
