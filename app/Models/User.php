<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
    ];

    public function admin()
    {
        return $this->hasOne(Admin::class, 'user_id');
    }
    
    public function driver()
    {
        return $this->hasOne(Driver::class, 'user_id');
    }

    public function generalUser()
    {
        return $this->hasOne(GeneralUser::class, 'user_id');
    }
}
