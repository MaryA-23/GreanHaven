<?php

namespace App\Services;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use App\Models\Payment;
class InventoryService
{
    /**
     * Reduce stock when order is placed
     */
    public function deductStock(Product $product, int $quantity)
    {
        if ($product->quantity < $quantity) {
            throw ValidationException::withMessages([
                'stock' => "{$product->name} insufficient stock"
            ]);
        }

        $product->quantity -= $quantity;

        // FIXED STATUS LOGIC
        if ($product->quantity <= 0) {
            $product->status = 'out_of_stock';
        } elseif ($product->quantity <= 5) {
            $product->status = 'low_stock';   // optional but VERY useful
        } else {
            $product->status = 'active';
        }

        $product->save();
    }

    public function addStock(Product $product, int $quantity)
    {
        $product->quantity += $quantity;
        
        if ($product->quantity > 0) {
            $product->status = 'active';           
        }
        
        $product->save();
    }

    public function expireOldPendingPayments()
{
    return Payment::where('status', 'pending')
        ->where('created_at', '<', now()->subMinutes(10))
        ->update([
            'status' => 'failed'
        ]);
}
}