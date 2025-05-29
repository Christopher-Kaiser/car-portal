<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftLog extends Model
{
    use HasFactory;

    public $timestamps = false;
    
    protected $fillable = [
        'driver_id',
        'shift_date',
        'on_duty',
    ];

    protected $casts = [
        'shift_date' => 'date',
        'on_duty' => 'boolean',
    ];

    // Relationships
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
