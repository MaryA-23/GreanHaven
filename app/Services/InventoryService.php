<?php

namespace App\Services;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
class InventoryService
{
    /**
     * Reduce stock when order is placed
     */
    public function deductStock(Product $product, int $quantity)
    {
        if ($product->quantity < $quantity) {
            throw ValidationException::withMessages([
                'stock' => "{$product->name} is out of stock or insufficient quantity"
            ]);
        }

        $product->quantity -= $quantity;

        // auto status update
        if ($product->quantity <= 0) {
            $product->quantity = 0;
            $product->status = 'out_of_stock';
        }

        $product->save();
    }

    /**
     * Add stock (restock)
     */
    public function addStock(Product $product, int $quantity)
    {
        $product->quantity += $quantity;

        if ($product->quantity > 0) {
            $product->status = 'active';
        }

        $product->save();
    }
}