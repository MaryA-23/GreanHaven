<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;

class Product extends Model
{
    use SoftDeletes, Notifiable;
    protected $fillable = [
        'company_id',
        'name',
        'price',
        'quantity',
        'category_id',
        'description',
        'image',
        'unit',
        'is_available',
        'status',
        'low_stock_threshold',
    ];
    protected $casts = [
    'price' => 'float',
    'is_available' => 'boolean',
        ];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

     public function company()
    {
        return $this->belongsTo(Company::class);
    }
 
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function isLowStock()
    {
        return $this->quantity <= $this->low_stock_threshold && $this->quantity > 0;
    }

    protected static function booted()
    {
        static::saving(function ($product) {
            if ($product->quantity <= 0) {
                $product->status = 'out_of_stock';
            } elseif ($product->quantity <= 5) {
                $product->status = 'low_stock';
            } else {
                $product->status = 'active';
            }
        });
    }

}

