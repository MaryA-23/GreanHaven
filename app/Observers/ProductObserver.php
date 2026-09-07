<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\TenantNotification;
use App\Services\TenantNotificationService;

class ProductObserver
{
    public function updated(
        Product $product
    ): void
    {
        if (
            !$product->wasChanged(
                'quantity'
            )
        ) {
            return;
        }


        if (!$product->company_id) {
            return;
        }


        $quantity =
            (int) $product->quantity;


        $threshold =
            (int) (
                $product->low_stock_threshold
                ?? 5
            );


        /*
        |--------------------------------------------------------------------------
        | OUT OF STOCK
        |--------------------------------------------------------------------------
        */

        if ($quantity <= 0) {

            $exists =
                TenantNotification::where(
                    'company_id',
                    $product->company_id
                )
                ->where(
                    'type',
                    'out_of_stock'
                )
                ->where(
                    'reference_id',
                    $product->id
                )
                ->where(
                    'is_read',
                    false
                )
                ->exists();


            if (!$exists) {

                app(
                    TenantNotificationService::class
                )
                ->outOfStock(
                    $product->company_id,
                    $product->id,
                    $product->name
                );

            }


            return;
        }


        /*
        |--------------------------------------------------------------------------
        | LOW STOCK
        |--------------------------------------------------------------------------
        */

        if (
            $quantity <= $threshold
        ) {

            $exists =
                TenantNotification::where(
                    'company_id',
                    $product->company_id
                )
                ->where(
                    'type',
                    'low_stock'
                )
                ->where(
                    'reference_id',
                    $product->id
                )
                ->where(
                    'is_read',
                    false
                )
                ->exists();


            if (!$exists) {

                app(
                    TenantNotificationService::class
                )
                ->lowStock(
                    $product->company_id,
                    $product->id,
                    $product->name,
                    $quantity
                );

            }

        }
    }
}