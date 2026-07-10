<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticable;

class Admin extends Authenticable   
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

// protected $connection = 'system';

    protected $fillable = [
        'uuid',
        'othernames',
        'surname',
        'fullname',
        'email',
        'phone',
        'password',
        'status',
        'role',
        'permissions',
        'profile_picture',  
    ];  

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'permissions' => 'array',
    ];
    

}
