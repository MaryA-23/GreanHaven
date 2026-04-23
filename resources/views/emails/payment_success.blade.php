<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - GreenHaven</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            background: #f4f4f4; 
            padding: 20px; 
        }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 40px 30px; text-align: center; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .content { padding: 40px 30px; }
        .order-card { background: #f8f9fa; border-radius: 8px; padding: 25px; margin: 25px 0; border-left: 5px solid #28a745; }
        .order-id { font-size: 24px; font-weight: 700; color: #28a745; margin-bottom: 15px; }
        .price { font-size: 32px; font-weight: 800; color: #28a745; }
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .detail { text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .detail-label { font-size: 14px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-value { font-size: 20px; font-weight: 600; color: #333; margin-top: 5px; }
        .button { 
            display: inline-block; 
            background: #007bff; 
            color: white; 
            padding: 15px 40px; 
            text-decoration: none; 
            border-radius: 50px; 
            font-weight: 600; 
            font-size: 16px; 
            margin: 25px 10px 0 0;
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        .button:hover { background: #0056b3; }
        .footer { background: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #eee; }
        .footer p { color: #666; margin: 5px 0; font-size: 14px; }
        @media (max-width: 600px) { .details-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>🎉 Payment Successful!</h1>
            <p>Your GreenHaven order is confirmed</p>
        </div>

        <!-- CONTENT -->
        <div class="content">
            <div class="order-card">
                <div class="order-id">Order #{{ $order->id }}</div>
                <div style="font-size: 18px; margin-bottom: 20px;">
                    Thank you for your purchase, {{ $user->name ?? 'Customer' }}!
                </div>
                
                <div class="details-grid">
                    <div class="detail">
                        <div class="detail-label">Total Paid</div>
                        <div class="detail-value price">${{ number_format($order->total_price, 2) }}</div>
                    </div>
                    <div class="detail">
                        <div class="detail-label">Order Date</div>
                        <div class="detail-value">{{ $order->created_at->format('M d, Y') }}</div>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ config('app.frontend_url') }}/orders/{{ $order->id }}" class="button">
                    View Order Details
                </a>
                <a href="{{ config('app.frontend_url') }}" class="button" style="background: #6c757d;">
                    Continue Shopping
                </a>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <p><strong>GreenHaven Team</strong></p>
            <p>Questions? Reply to this email or contact support@greenhaven.com</p>
            <p style="font-size: 12px;">© 2026 GreenHaven. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
