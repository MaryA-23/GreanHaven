<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Vegetable extends Model
{
    use SoftDeletes, Notifiable;
    protected $fillable = [
        'name', 'status', 'customer_name', 'customer_contact', 'request_status'
    ];

    public function requests()
    {
        return $this->hasMany(VegetableRequest::class);
    }
}

