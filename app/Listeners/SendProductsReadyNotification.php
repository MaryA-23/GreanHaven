<?php

namespace App\Listeners;

use App\Events\ProductReady;
use App\Notifications\ProductReadyNotification;
use Illuminate\Support\Facades\Log;

class SendProductReadyNotification
{
    public function handle(ProductReady $event)
    {
        $pro = $event->Product;

        if ($pro->customer_name && $pro->customer_contact && $pro->request_status === 'pending') {
            // Send email notification
            $pro->notify(new ProductReadyNotification($pro));

            // Update request_status to in_progress
            $pro->update(['request_status' => 'in_progress']);

            Log::info("Notification sent to {$pro->customer_name} for product {$pro->name}");
        }
    }
}
