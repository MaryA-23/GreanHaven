<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\TenantNotification;
use App\Services\TenantNotificationService;

class PaymentObserver
{
    public function updated(
        Payment $payment
    ): void
    {
        if (
            !$payment->wasChanged(
                'status'
            )
        ) {
            return;
        }


        if (
            strtolower(
                (string) $payment->status
            ) !== 'paid'
        ) {
            return;
        }


        $payment->loadMissing(
            'order'
        );


        $order =
            $payment->order;


        if (
            !$order ||
            !$order->company_id
        ) {
            return;
        }


        $alreadyExists =
            TenantNotification::where(
                'company_id',
                $order->company_id
            )
            ->where(
                'type',
                'payment_received'
            )
            ->where(
                'reference_id',
                $order->id
            )
            ->exists();


        if ($alreadyExists) {
            return;
        }


        app(
            TenantNotificationService::class
        )
        ->paymentReceived(
            $order->company_id,
            $order->id
        );
    }
}