<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\TenantNotificationService;

class OrderObserver
{
    public function created(
        Order $order
    ): void
    {
        if (!$order->company_id) {
            return;
        }


        $notificationService =
            app(
                TenantNotificationService::class
            );


        $alreadyExists =
            \App\Models\TenantNotification::where(
                'company_id',
                $order->company_id
            )
            ->where(
                'type',
                'new_order'
            )
            ->where(
                'reference_id',
                $order->id
            )
            ->exists();


        if ($alreadyExists) {
            return;
        }


        $notificationService
            ->newOrder(
                $order->company_id,
                $order->id
            );
    }
}