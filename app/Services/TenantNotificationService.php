<?php

namespace App\Services;

use App\Models\TenantNotification;

class TenantNotificationService
{
    public function create(
        int $companyId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?int $referenceId = null
    ): TenantNotification
    {
        return TenantNotification::create([
            'company_id' => $companyId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'reference_id' => $referenceId,
            'is_read' => false,
        ]);
    }


    public function newOrder(
        int $companyId,
        int $orderId
    ): TenantNotification
    {
        return $this->create(
            $companyId,
            'new_order',
            'New Order',
            "A new order #{$orderId} has been placed.",
            '/tenant/orders',
            $orderId
        );
    }


    public function paymentReceived(
        int $companyId,
        int $orderId
    ): TenantNotification
    {
        return $this->create(
            $companyId,
            'payment_received',
            'Payment Received',
            "Payment for order #{$orderId} was successful.",
            '/tenant/payments',
            $orderId
        );
    }


    public function lowStock(
        int $companyId,
        int $productId,
        string $productName,
        int $quantity
    ): TenantNotification
    {
        return $this->create(
            $companyId,
            'low_stock',
            'Low Stock',
            "{$productName} is running low. {$quantity} remaining.",
            '/tenant/inventory',
            $productId
        );
    }


    public function outOfStock(
        int $companyId,
        int $productId,
        string $productName
    ): TenantNotification
    {
        return $this->create(
            $companyId,
            'out_of_stock',
            'Out of Stock',
            "{$productName} is now out of stock.",
            '/tenant/inventory',
            $productId
        );
    }
}