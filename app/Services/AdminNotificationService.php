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

    /**
     * Tenant subscription payment/problem alert.
     */
    public function subscriptionProblem(
        int $subscriptionId,
        int $companyId,
        string $companyName,
        string $message
    ): AdminNotification {
        return $this->create(
            'subscription_problem',
            'Tenant Subscription Problem',
            "{$companyName}: {$message}",
            '/super-admin/subscriptions',
            $subscriptionId
        );
    }

    /**
     * General platform error alert.
     */
    public function systemProblem(
        string $portal,
        string $area,
        string $message,
        ?string $actionUrl = null,
        ?int $referenceId = null
    ): AdminNotification {
        return $this->create(
            'system_problem',
            'Platform Issue Detected',
            "[{$portal}] {$area}: {$message}",
            $actionUrl,
            $referenceId
        );
    }
}
