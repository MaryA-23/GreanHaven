<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Greenhaven, {{ $user->name ?? 'Friend' }}!</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            line-height: 1.6; color: #2d3748; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            padding: 40px 20px; min-height: 100vh; 
        }
        .container { max-width: 550px; margin: 0 auto; background: white; border-radius: 24px; overflow: hidden; box-shadow: 0 25px 70px rgba(0,0,0,0.2); }
        .hero { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 60px 40px; text-align: center; position: relative; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -50%; right: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%); animation: float 6s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-20px) rotate(180deg); } }
        .hero h1 { font-size: 40px; font-weight: 800; margin-bottom: 15px; position: relative; z-index: 2; }
        .hero .name { background: linear-gradient(45deg, #fff, #f8fafc); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-size: 48px; display: block; margin-bottom: 10px; }
        .content { padding: 60px 50px; text-align: center; }
        .welcome-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 20px; margin: 40px 0; position: relative; overflow: hidden; }
        .welcome-card::before { content: '✨'; position: absolute; font-size: 80px; top: -20px; right: -20px; opacity: 0.1; }
        .welcome-card h2 { font-size: 32px; margin-bottom: 20px; }
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 25px; margin: 50px 0; }
        .feature { background: rgba(255,255,255,0.95); padding: 30px 25px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); transition: transform 0.3s ease; }
        .feature:hover { transform: translateY(-8px); }
        .feature-icon { font-size: 48px; margin-bottom: 15px; }
        .feature h3 { font-size: 22px; margin-bottom: 10px; color: #2d3748; }
        .cta-button { 
            display: inline-block; background: linear-gradient(135deg, #48bb78, #38a169); 
            color: white; padding: 22px 50px; text-decoration: none; border-radius: 50px; 
            font-weight: 700; font-size: 20px; box-shadow: 0 15px 40px rgba(72,187,120,0.4); 
            margin: 40px auto; transition: all 0.3s ease; 
        }
        .cta-button:hover { transform: translateY(-4px); box-shadow: 0 20px 50px rgba(72,187,120,0.5); }
        .footer { background: #f7fafc; padding: 40px 30px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer p { color: #718096; margin: 10px 0; font-size: 16px; }
        @media (max-width: 600px) { .content { padding: 40px 25px; } .hero h1 { font-size: 32px; } }
    </style>
</head>
<body>
    <div class="container">
        <!-- HERO -->
        <div class="hero">
            <h1>Welcome <span class="name">{{ $user->name ?? 'Friend' }}</span></h1>
            <p>You’re now part of the Greenhaven family! 🌱</p>
        </div>

        <!-- CONTENT -->
        <div class="content">
            <div class="welcome-card">
                <h2>✨ Your Journey Begins!</h2>
                <p style="font-size: 18px; opacity: 0.95; margin-bottom: 20px;">
                    Your account is ready! Please verify your email to start shopping fresh produce.
                </p>
            </div>

            <!-- FEATURES -->
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">🛒</div>
                    <h3>Fresh Produce</h3>
                    <p>Farm-fresh vegetables delivered to your door</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">🚚</div>
                    <h3>Fast Delivery</h3>
                    <p>Quick & reliable delivery in your area</p>
                </div>
                <div class="feature">
                    <div class="feature-icon">💚</div>
                    <h3>Local Farmers</h3>
                    <p>Supporting local farmers & sustainability</p>
                </div>
            </div>

            <!-- CTA -->
            <div style="text-align: center; margin: 60px 0;">
                <a href="{{ $verifyUrl ?? '#' }}" class="cta-button">
                    ✅ Verify Email & Start Shopping
                </a>
            </div>
        </div>

        <!-- FOOTER -->
        <div class="footer">
            <p>👋 <strong>Happy Farming!</strong></p>
            <p>Greenhaven Team | <a href="mailto:hello@greenhaven.com" style="color: #4299e1;">hello@greenhaven.com</a></p>
            <p style="font-size: 14px; color: #a0aec0;">© 2025 Greenhaven. Fresh from farm to table.</p>
        </div>
    </div>
</body>
</html>