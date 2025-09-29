<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VegetableRequest extends Model
{
    use HasFactory;

    protected $fillable = ['vegetable_id', 'customer_name', 'customer_contact', 'status'];

    public function vegetable()
    {
        return $this->belongsTo(Vegetable::class);
    }
}
