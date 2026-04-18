<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use SoftDeletes, Notifiable;
    protected $fillable = [
        'name',
        'status',
        'price',
        'quantity',
        'category_id',
        'description',
        'unit',
        'is_available',
   
    ];

    protected $casts = [
    'price' => 'float',
    'is_available' => 'boolean',
        ];

    public function orderItems()
{
    return $this->hasMany(OrderItem::class);
}
 
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

}

