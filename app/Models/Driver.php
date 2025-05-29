<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Driver extends Model
{
    use HasFactory, Notifiable;

    public $timestamps = false;

    protected $table = 'drivers';

    protected $fillable = [
        'user_id',
        'name',  
        'phone_number',
        'status',        
        'latitude',
        'longitude',
        'license_number',
        'license_issued_at',
        'license_expires_at',
    ];

    protected $casts = [
        'status' => 'string',
        'latitude' => 'float',
        'longitude' => 'float',
        'license_issued_at' => 'date',
        'license_expires_at' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shifts()
    {
        return $this->hasMany(Shift::class, 'driver_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function carRequests()
    {
        return $this->hasMany(CarRequest::class);
    }
}
