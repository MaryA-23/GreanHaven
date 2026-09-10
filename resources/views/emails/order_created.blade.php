<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenHaven Order Created</title>
</head>

<body style="
    margin:0;
    padding:0;
    background-color:#f3f6f4;
    font-family:Arial, Helvetica, sans-serif;
    color:#1f2937;
">

<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
    <tr>
        <td align="center" style="padding:40px 15px;">

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                role="presentation"
                style="
                    max-width:600px;
                    background:#ffffff;
                    border-radius:16px;
                    overflow:hidden;
                    box-shadow:0 8px 25px rgba(0,0,0,0.08);
                "
            >

                <!-- Header -->
                <tr>
                    <td
                        align="center"
                        style="
                            background:#14532d;
                            padding:35px 25px;
                            color:#ffffff;
                        "
                    >
                        <div style="font-size:28px; font-weight:700;">
                            GreenHaven
                        </div>

                        <div
                            style="
                                margin-top:6px;
                                font-size:14px;
                                color:#bbf7d0;
                            "
                        >
                            Your order has been created
                        </div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style="padding:40px 35px;">

                        <h2
                            style="
                                margin:0 0 12px;
                                font-size:24px;
                                color:#14532d;
                            "
                        >
                            Order #{{ $order->id }}
                        </h2>

                        <p
                            style="
                                margin:0 0 25px;
                                font-size:16px;
                                line-height:1.7;
                                color:#4b5563;
                            "
                        >
                            Hello {{ $order->user->first_name ?? 'Customer' }},
                            your GreenHaven order has been created successfully.
                        </p>

                        <!-- Order Summary -->
                        <table
                            width="100%"
                            cellpadding="0"
                            cellspacing="0"
                            role="presentation"
                            style="
                                background:#f0fdf4;
                                border:1px solid #dcfce7;
                                border-radius:12px;
                                margin-bottom:30px;
                            "
                        >
                            <tr>
                                <td style="padding:25px;">

                                    <div
                                        style="
                                            font-size:13px;
                                            color:#6b7280;
                                            margin-bottom:6px;
                                        "
                                    >
                                        ORDER NUMBER
                                    </div>

                                    <div
                                        style="
                                            font-size:18px;
                                            font-weight:700;
                                            color:#14532d;
                                            margin-bottom:22px;
                                        "
                                    >
                                        #{{ $order->id }}
                                    </div>

                                    <div
                                        style="
                                            font-size:13px;
                                            color:#6b7280;
                                            margin-bottom:6px;
                                        "
                                    >
                                        TOTAL AMOUNT
                                    </div>

                                    <div
                                        style="
                                            font-size:28px;
                                            font-weight:700;
                                            color:#15803d;
                                        "
                                    >
                                        GHS {{ number_format($order->total_price, 2) }}
                                    </div>

                                </td>
                            </tr>
                        </table>

                        <p
                            style="
                                margin:0;
                                font-size:15px;
                                line-height:1.7;
                                color:#4b5563;
                            "
                        >
                            Your order is currently waiting for payment.
                            Once payment is completed, your order will be confirmed.
                        </p>

                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td
                        align="center"
                        style="
                            background:#f9fafb;
                            padding:25px;
                            border-top:1px solid #e5e7eb;
                        "
                    >
                        <p
                            style="
                                margin:0 0 6px;
                                font-size:14px;
                                font-weight:600;
                                color:#14532d;
                            "
                        >
                            GreenHaven Team
                        </p>

                        <p
                            style="
                                margin:0;
                                font-size:12px;
                                color:#9ca3af;
                            "
                        >
                            © {{ date('Y') }} GreenHaven. All rights reserved.
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>