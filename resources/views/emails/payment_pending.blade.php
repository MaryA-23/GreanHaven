<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Payment Pending</title>
</head>
<body>

    <h2>Order Received 🛒</h2>

    <p>Your order #{{ $order->id }} has been created successfully.</p>

    <p>Total Amount: {{ $order->total_price }}</p>

    <p>Status: Pending Payment</p>

    <p>Click below to complete payment:</p>

    <a href="{{ url('/api/payments/paystack/initialize?order_id=' . $order->id) }}">
    Pay Now
</a>

    <p>If you have any questions, please contact our support team.</p>

    <p>Thank you for shopping with us!</p>

</body>
</html>