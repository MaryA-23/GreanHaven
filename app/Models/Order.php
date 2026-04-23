<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'status',
        'total_price',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
       Order::with(['user', 'payments']);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }


    // Optional: Access payment status easily
    public function getPaymentStatusAttribute()
    {
        return $this->payment?->status ?? 'unpaid';
    }

}
