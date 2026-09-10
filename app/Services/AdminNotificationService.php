<?php

namespace App\Services;

use App\Models\AdminNotification;

class AdminNotificationService
{
    /**
     * Create a general Admin/Super Admin notification.
     */
    public function create(
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?int $referenceId = null
    ): AdminNotification {
        return AdminNotification::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'reference_id' => $referenceId,
            'is_read' => false,
        ]);
    }

    /**
     * New tenant/company registration.
     */
    public function newTenant(
        int $companyId,
        string $companyName
    ): AdminNotification {
        return $this->create(
            'new_tenant',
            'New Tenant Registration',
            "{$companyName} has registered on GreenHaven.",
            null,
            $companyId
        );
    }

    /**
     * New customer order.
     */
    public function newOrder(
        int $orderId,
        ?string $companyName = null
    ): AdminNotification {
        $message = "A new customer order #{$orderId} has been created.";

        if ($companyName) {
            $message = "A new customer order #{$orderId} has been placed with {$companyName}.";
        }

        return $this->create(
            'new_order',
            'New Customer Order',
            $message,
            null,
            $orderId
        );
    }

    /**
     * Successful payment.
     */
    public function paymentSuccessful(
        int $paymentId,
        int $orderId,
        float $amount
    ): AdminNotification {
        return $this->create(
            'payment_successful',
            'Payment Successful',
            "Payment for order #{$orderId} was successful. Amount: {$amount}.",
            null,
            $paymentId
        );
    }

    /**
     * Failed payment.
     */
    public function paymentFailed(
        int $paymentId,
        int $orderId,
        float $amount
    ): AdminNotification {
        return $this->create(
            'payment_failed',
            'Payment Failed',
            "Payment for order #{$orderId} failed. Amount: {$amount}.",
            null,
            $paymentId
        );
    }

    /**
     * Low-stock product.
     */
    public function lowStock(
        int $productId,
        string $productName,
        int $quantity,
        ?string $companyName = null
    ): AdminNotification {
        $message = "{$productName} is running low. {$quantity} remaining.";

        if ($companyName) {
            $message = "{$productName} from {$companyName} is running low. {$quantity} remaining.";
        }

        return $this->create(
            'low_stock',
            'Low Stock',
            $message,
            null,
            $productId
        );
    }

    /**
     * Out-of-stock product.
     */
    public function outOfStock(
        int $productId,
        string $productName,
        ?string $companyName = null
    ): AdminNotification {
        $message = "{$productName} is now out of stock.";

        if ($companyName) {
            $message = "{$productName} from {$companyName} is now out of stock.";
        }

        return $this->create(
            'out_of_stock',
            'Out of Stock',
            $message,
            null,
            $productId
        );
    }
}