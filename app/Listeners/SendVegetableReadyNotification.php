<?php

namespace App\Listeners;

use App\Events\VegetableReady;
use App\Notifications\VegetableReadyNotification;
use Illuminate\Support\Facades\Log;

class SendVegetableReadyNotification
{
    public function handle(VegetableReady $event)
    {
        $veg = $event->vegetable;

        if ($veg->customer_name && $veg->customer_contact && $veg->request_status === 'pending') {
            // Send email notification
            $veg->notify(new VegetableReadyNotification($veg));

            // Update request_status to in_progress
            $veg->update(['request_status' => 'in_progress']);

            Log::info("Notification sent to {$veg->customer_name} for vegetable {$veg->name}");
        }
    }
}
