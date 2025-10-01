<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Vegetable extends Model
{
    use SoftDeletes, Notifiable;
    protected $fillable = [
        'name',
        'status',
        'price',
        'quantity',
        'category',
        'description',
        'unit',
        'is_available',
   
    ];

    public function requests()
    {
        return $this->hasMany(VegetableRequest::class);
    }

    public function orderItems()
{
    return $this->hasMany(OrderItem::class);
}

}

