<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Check if stock is enough before creating order.
     * This does NOT reduce stock.
     */
    public function checkStock(Product $product, int $quantity): void
    {
        $this->syncStatus($product);

        if ($product->status === 'inactive' || $product->status === 'out_of_stock' || !$product->is_available) {
            throw ValidationException::withMessages([
                'stock' => "{$product->name} is not available."
            ]);
        }

        if ($product->quantity < $quantity) {
            throw ValidationException::withMessages([
                'stock' => "{$product->name} has insufficient stock. Available: {$product->quantity}, requested: {$quantity}."
            ]);
        }
    }

    /**
     * Reduce stock only after successful payment.
     */
    public function deductStock(Product $product, int $quantity): void
    {
        if ($product->quantity < $quantity) {
            throw ValidationException::withMessages([
                'stock' => "{$product->name} has insufficient stock."
            ]);
        }

        $product->quantity -= $quantity;

        $this->syncStatus($product);

        $product->save();
    }

    /**
     * Add stock back only for refunds/returns/manual restock.
     * Do not use this for unpaid payment expiry anymore.
     */
    public function addStock(Product $product, int $quantity): void
    {
        $product->quantity += $quantity;

        $this->syncStatus($product);

        $product->save();
    }

        private function syncStatus(Product $product)
        {
            $threshold = $product->low_stock_threshold ?? 5;

            if ($product->quantity <= 0) {
                $product->quantity = 0;
                $product->status = 'out_of_stock';
                $product->is_available = false;
            } elseif ($product->quantity <= $threshold) {
                $product->status = 'low_stock';
                $product->is_available = true;
            } else {
                $product->status = 'active';
                $product->is_available = true;
            }
        }
}