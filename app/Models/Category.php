<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;
use App\Models\Product;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'image'
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
    public function company()
{
    return $this->belongsTo(Company::class);
}
}
