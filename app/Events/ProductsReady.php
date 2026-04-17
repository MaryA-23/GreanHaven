<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductReady
{
    use Dispatchable, SerializesModels;

    public $Product;

    public function __construct(Product $Product)
    {
        $this->Product = $Product;
    }
}