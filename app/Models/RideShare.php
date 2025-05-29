<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideShare extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'request_id',
        'status', // pending, accepted, rejected, completed
        'pickup_location',
        'dropoff_location',
        'pickup_coordinates', // latitude,longitude
        'dropoff_coordinates', // latitude,longitude
        'passenger_count',
        'estimated_pickup_time',
        'estimated_dropoff_time',
        'detour_distance', // in meters
        'detour_duration', // in seconds
        'fare',
        'notes'
    ];

    protected $casts = [
        'pickup_coordinates' => 'array',
        'dropoff_coordinates' => 'array',
        'estimated_pickup_time' => 'datetime',
        'estimated_dropoff_time' => 'datetime',
        'detour_distance' => 'integer',
        'detour_duration' => 'integer',
        'fare' => 'decimal:2'
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function request()
    {
        return $this->belongsTo(CarRequest::class, 'request_id');
    }
} 