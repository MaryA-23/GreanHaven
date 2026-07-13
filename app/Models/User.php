<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name', 
        'last_name',
        'email',
        'password',
        'role',           
        'company_id',
        'last_login',
        'status',
        'phone',
        'gender',
        'profile_picture',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime:Y-m-d H:i:s',
            'password' => 'hashed',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if ($user->first_name && $user->last_name) {
                $user->name = trim($user->first_name . ' ' . $user->last_name);
            }
        });

        static::updating(function ($user) {
            if ($user->first_name && $user->last_name) {
                $user->name = trim($user->first_name . ' ' . $user->last_name);
            }
        });
    }
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function orders() {
        return $this->hasMany(Order::class);
    }

    public function company() {
        return $this->belongsTo(Company::class);
    }
    
    public function isAdmin() {
        return $this->role === 'admin';
    }
    
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function cart()
    {
        return $this->hasOne(Cart::class);
    }
}
