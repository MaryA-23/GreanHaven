<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
    'order_id',
    'user_id',
    'amount',
    'status',
    'payment_method',
    'gateway_reference',
    'paid_at',
    'expires_at',
    'expired_at',
    'notes',
    ];

    protected $casts = [
    'amount' => 'decimal:2',
    'paid_at' => 'datetime',
    'expires_at' => 'datetime',
    'expired_at' => 'datetime',
    ];
        // Relationships
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes for easy querying (e.g., in API)
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
