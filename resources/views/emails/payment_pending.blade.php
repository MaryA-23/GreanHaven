 <!DOCTYPE html>
 <html lang='en'>
 <head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Payment Pending</title>
    <link rel='stylesheet' href='main.css'>
 </head>
 <body>
    <h2>Order Received 🛒</h2>

<p>Your order #{{ $order->id }} has been created successfully.</p>

<p>Total Amount: {{ $order->total_price }}</p>

<p>Status: Pending Payment</p>
 </body>
 </html>