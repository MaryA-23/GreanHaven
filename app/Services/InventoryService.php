<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private AdminNotificationService $adminNotificationService
    ) {
    }

    /**
     * Check if stock is enough before creating order.
     * This does NOT reduce stock.
     */
    public function checkStock( Product $product,  int $quantity): void
    {
        $this->syncStatus(
            $product
        );

        if (
            $product->status === 'inactive'
            ||
            $product->status === 'out_of_stock'
            ||
            !$product->is_available
        ) {

            throw ValidationException::withMessages([
                'stock' =>
                    "{$product->name} is not available."
            ]);
        }

        if (
            $product->quantity <
            $quantity
        ) {

            throw ValidationException::withMessages([
                'stock' =>
                    "{$product->name} has insufficient stock. Available: {$product->quantity}, requested: {$quantity}."
            ]);
        }
    }


    /**
     * Reduce stock only after successful payment.
     */
    public function deductStock(Product $product, int $quantity ): void
    {
        if (
            $product->quantity <
            $quantity
        ) {

            throw ValidationException::withMessages([
                'stock' =>
                    "{$product->name} has insufficient stock."
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Remember old stock status
        |--------------------------------------------------------------------------
        */

        $oldStatus =
            $product->status;


        /*
        |--------------------------------------------------------------------------
        | Reduce quantity
        |--------------------------------------------------------------------------
        */

        $product->quantity -=
            $quantity;


        /*
        |--------------------------------------------------------------------------
        | Recalculate stock status
        |--------------------------------------------------------------------------
        */

        $this->syncStatus(
            $product
        );


        $product->save();


        /*
        |--------------------------------------------------------------------------
        | Admin / Super Admin Low Stock Notification
        |--------------------------------------------------------------------------
        |
        | Notify only when the product ENTERS low stock.
        |
        | Example:
        | active -> low_stock = notify
        | low_stock -> low_stock = do not notify again
        |
        */

        if (
            $oldStatus !== 'low_stock'
            &&
            $product->status === 'low_stock'
        ) {

            try {

                $this->adminNotificationService
                    ->lowStock(
                        $product->id,
                        $product->name,
                        (int) $product->quantity
                    );

            } catch (
                \Exception $notificationException
            ) {

                Log::error(
                    'Admin low stock notification failed',
                    [
                        'message' =>
                            $notificationException
                                ->getMessage(),

                        'product_id' =>
                            $product->id,

                        'product_name' =>
                            $product->name,

                        'quantity' =>
                            $product->quantity,
                    ]
                );
            }
        }
    }


    /**
     * Add stock back only for refunds/returns/manual restock.
     * Do not use this for unpaid payment expiry anymore.
     */
    public function addStock( Product $product, int $quantity): void
    {
        $product->quantity +=
            $quantity;


        $this->syncStatus(
            $product
        );


        $product->save();
    }


    /**
     * Set product stock status.
     */
    public function syncStatus( Product $product ): void
    {
        $threshold =
            $product->low_stock_threshold
            ?? 5;


        /*
        |--------------------------------------------------------------------------
        | Out of stock
        |--------------------------------------------------------------------------
        */

        if (
            $product->quantity <= 0
        ) {

            $product->quantity =
                0;

            $product->status =
                'out_of_stock';

            $product->is_available =
                false;
        }

        /*
        |--------------------------------------------------------------------------
        | Low stock
        |--------------------------------------------------------------------------
        */

        elseif (
            $product->quantity <=
            $threshold
        ) {

            $product->status =
                'low_stock';

            $product->is_available =
                true;
        }

        /*
        |--------------------------------------------------------------------------
        | In stock
        |--------------------------------------------------------------------------
        */

        else {

            $product->status =
                'active';

            $product->is_available =
                true;
        }
    }
}