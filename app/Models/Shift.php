<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    public $timestamps = false;
    
    protected $fillable = [
        'driver_id',
        'car_id',
        'shift_start',
        'shift_end',
        'is_active',
    ];

    // Cast shift_start and shift_end as Carbon instances
    protected $casts = [
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function car()
    {
        return $this->belongsTo(Car::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }
}
