 cat > resources/views/emails/payment_success.blade.php << 'EOF'
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Payment Successful</title>
        <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        h2 { color: #28a745; }
    </style>
            </head>
    <body>
        <h2>Payment Successful 🎉</h2>
    
    <p>Dear Customer,</p>
    
    <p>Your payment for <strong>Order #{{ $order->id }}</strong> has been confirmed.</p>
    
    <p><strong>Total Paid:</strong> ${{ number_format($order->total_price, 2) }}</p>
    
    <p>Thank you for shopping with GreenHaven!</p>
    
    <hr>
    
    <small>Order Date: {{ $order->created_at->format('M d, Y') }}</small>
</body>
</html>
EOF

