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

        $this->syncStatus($product);

        $product->save();
    }

    public function addStock(Product $product, int $quantity)
    {
        $product->quantity += $quantity;

        $this->syncStatus($product);

        $product->save();

    }

     public function expireOldPendingPayments()
    {
        $payments = Payment::with('order.items.product')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes(10))
            ->get();

        foreach ($payments as $payment) {

            $payment->update(['status' => 'failed']);

            // RESTORE STOCK
            foreach ($payment->order->items as $item) {
                $this->addStock($item->product, $item->quantity);
            }
        }
    }

    private function syncStatus(Product $product)
    {
        if ($product->quantity <= 0) {
            $product->status = 'out_of_stock';
        } elseif ($product->quantity <= 5) {
            $product->status = 'low_stock';
        } else {
            $product->status = 'active';
        }
    }
}