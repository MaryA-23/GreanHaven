<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class TenantNotification extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'title',
        'message',
        'action_url',
        'reference_id',
        'is_read',
        'read_at',
    ];


    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];


    public function company(): BelongsTo
    {
        return $this->belongsTo(
            Company::class
        );
    }
}