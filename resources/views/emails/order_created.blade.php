<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order Created - GreenHaven</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); overflow: hidden; }
        .header { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 40px 30px; text-align: center; }
        .content { padding: 40px 30px; }
        .order-id { font-size: 32px; font-weight: 700; color: #28a745; margin-bottom: 10px; }
        .total { font-size: 28px; color: #28a745; font-weight: bold; }
        .button { background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛒 Order Created Successfully!</h1>
            <p>Your GreenHaven order is ready</p>
        </div>
        
        <div class="content">
            <div style="text-align: center; margin-bottom: 30px;">
                <div class="order-id">#{{ $order->id }}</div>
                <p>Hi {{ $order->user->name ?? 'Customer' }},</p>
                <p>Your order has been created! Complete payment to confirm.</p>
            </div>
            
            <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; text-align: center; margin: 30px 0;">
                <div class="total">${{ number_format($order->total_price, 2) }}</div>
                <p>Total Amount</p>
            </div>
            
            <div style="text-align: center;">
                <a href="{{ url('/api/paystack/initialize?order_id=' . $order->id) }}" class="button">
                    💳 Pay Now
                </a>
            </div>
            <p>Thank you,<br>Greenhaven Team</p>
        </div>
    </div>
</body>
</html>
