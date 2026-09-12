<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'months',
        'amount',
        'currency',
        'reference',
        'status',
        'source',
        'admin_note',
        'granted_by_uuid',
        'source_subscription_id',
        'starts_at',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function sourceSubscription()
    {
        return $this->belongsTo(
            TenantSubscription::class,
            'source_subscription_id'
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'paid'
            && $this->expires_at
            && $this->expires_at->isFuture();
    }
}
