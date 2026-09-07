<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\TenantNotification;

class Company extends Model
{
    use HasFactory;
   protected $fillable = [
    'name',
    'email',
    'phone',
    'address',
    'city',
    'currency',
    'notification_settings',
    ];

    protected $casts = [
    'notification_settings' => 'array',
    ];

    public function users() {
        return $this->hasMany(User::class);
    }

    public function orders() {
        return $this->hasMany(Order::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function notifications()
    {
        return $this->hasMany(
            TenantNotification::class
        );
    }   
}
