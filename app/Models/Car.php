<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'license_plate',
        'brand',
        'model',
        'status',
    ];

    // Relationships
    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function drivers()
    {
        return $this->belongsTo(Driver::class);
    }
}
