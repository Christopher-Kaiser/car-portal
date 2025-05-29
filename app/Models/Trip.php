<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    public $timestamps = false;
    
    protected $fillable = [
        'driver_id',
        'shift_id',
        'request_id',
        'status',
        'started_at',
        'ended_at',
    ];

    // Relationships
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function carRequest()
    {
        return $this->belongsTo(CarRequest::class, 'request_id');
    }
}