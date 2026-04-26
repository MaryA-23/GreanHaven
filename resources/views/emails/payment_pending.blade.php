<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Greenhaven Order Payment Details</title>
</head>
<body>
    <p>Hello {{ $order->user->name ?? 'Customer' }},</p>

    <p>Thank you for placing an order with Greenhaven.</p>

    <p><strong>Order Number:</strong> #{{ $order->id }}</p>
    <p><strong>Amount Due:</strong> GHS {{ number_format($order->total_price, 2) }}</p>
    <p><strong>Payment Expiry:</strong> {{ optional($payment->expires_at)->format('d M Y, h:i A') }}</p>

    <p>Please click below to complete your payment securely:</p>

    <p>
        <a href="{{ $paymentUrl }}">Complete Payment</a>
    </p>

    <p>If payment is not completed before the expiry time, the order will automatically expire.</p>

    <p>Thank you,<br>Greenhaven Team</p>
</body>
</html>