<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Greenhaven Order Payment</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            line-height: 1.6; color: #333; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            padding: 20px; min-height: 100vh; 
        }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        .header { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 50px 30px; text-align: center; }
        .header h1 { font-size: 32px; margin-bottom: 10px; font-weight: 700; }
        .header p { opacity: 0.95; font-size: 18px; }
        .content { padding: 50px 40px; }
        .order-card { background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 16px; padding: 35px; margin: 30px 0; text-align: center; box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
        .order-number { font-size: 42px; font-weight: 800; background: linear-gradient(135deg, #28a745, #20c997); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 20px; }
        .amount { font-size: 36px; font-weight: 800; color: #28a745; margin: 20px 0 10px; }
        .currency { font-size: 24px; color: #666; font-weight: 600; }
        .expiry { background: #fff3cd; color: #856404; padding: 12px 24px; border-radius: 50px; display: inline-block; font-weight: 600; margin: 20px 0; }
        .pay-button { 
            display: inline-block; background: linear-gradient(135deg, #007bff, #0056b3); 
            color: white; padding: 20px 50px; text-decoration: none; border-radius: 50px; 
            font-weight: 700; font-size: 20px; box-shadow: 0 12px 35px rgba(0,123,255,0.4); 
            margin: 40px auto; transition: all 0.3s ease; 
        }
        .pay-button:hover { transform: translateY(-3px); box-shadow: 0 18px 45px rgba(0,123,255,0.5); }
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 40px 0; }
        .feature { text-align: center; padding: 25px; background: white; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.06); }
        .feature-icon { font-size: 36px; margin-bottom: 10px; }
        .footer { background: #f8f9fa; padding: 40px 30px; text-align: center; border-top: 1px solid #e9ecef; }
        .footer p { color: #666; margin: 8px 0; font-size: 15px; }
        @media (max-width: 600px) { .content { padding: 30px 20px; } .order-number { font-size: 32px; } }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>🛒 Complete Your Payment</h1>
            <p>Your Greenhaven order is waiting!</p>
        </div>

        <!-- ORDER INFO -->
        <div class="content">
            <div class="order-card">
                <div class="order-number">#{{ $order->id }}</div>
                <div class="amount">
                    GHS <span class="currency">{{ number_format($order->total_price, 2) }}</span>
                </div>
                
                <div class="expiry">
                    ⏰ Expires: {{ optional($payment->expires_at)->format('d M Y, h:i A') ?? '24 hours' }}
                </div>
                
                <p style="margin: 30px 0; font-size: 18px; color: #555;">
                    Secure checkout with <strong>Paystack</strong>
                </p>
            </div>

            <!-- BIG PAY BUTTON -->
            <div style="text-align: center; margin: 50px 0;">
                <a href="{{ $paymentUrl }}" class="pay-button">
                    💳 Pay Securely Now
                </a>
            </div>

            <!-- TRUST FEATURES -->
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">🔒</div>
                    <strong>Secure</strong><br>
                    SSL Encrypted
                </div>
                <div class="feature-icon">⚡</div>
                <div class="feature">
                    <div class="feature-icon">⚡</div>
                    <strong>Instant</strong><br>
                    Confirmation
                </div>
                <div class="feature">
                    <div class="feature-icon">🛡️</div>
                    <strong>Protected</strong><br>
                    Paystack Verified
                </div>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <p>👋 <strong>Greenhaven Team</strong></p>
            <p>Questions? Reply to this email or contact <a href="mailto:support@greenhaven.com">support@greenhaven.com</a></p>
            <p style="font-size: 13px; color: #999;">© 2025 Greenhaven. Order #{{ $order->id }}</p>
        </div>
    </div>
</body>
</html>