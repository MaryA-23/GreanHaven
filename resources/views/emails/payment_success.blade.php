<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - GreenHaven</title>
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
                        <div
                            style="
                                width:58px;
                                height:58px;
                                line-height:58px;
                                margin:0 auto 15px;
                                border-radius:50%;
                                background:#ffffff;
                                color:#15803d;
                                font-size:30px;
                                font-weight:700;
                            "
                        >
                            ✓
                        </div>

                        <div
                            style="
                                font-size:28px;
                                font-weight:700;
                            "
                        >
                            Payment Successful
                        </div>

                        <div
                            style="
                                margin-top:7px;
                                font-size:14px;
                                color:#bbf7d0;
                            "
                        >
                            Your GreenHaven order has been confirmed
                        </div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style="padding:40px 35px;">

                        <h2
                            style="
                                margin:0 0 12px;
                                font-size:22px;
                                color:#14532d;
                            "
                        >
                            Thank you, {{ $user->first_name ?? 'Customer' }}
                        </h2>

                        <p
                            style="
                                margin:0 0 28px;
                                font-size:16px;
                                line-height:1.7;
                                color:#4b5563;
                            "
                        >
                            We have received your payment successfully.
                            Your order is now confirmed.
                        </p>

                        <!-- Payment Summary -->
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

                                    <table
                                        width="100%"
                                        cellpadding="0"
                                        cellspacing="0"
                                        role="presentation"
                                    >
                                        <tr>
                                            <td
                                                style="
                                                    padding-bottom:18px;
                                                    font-size:14px;
                                                    color:#6b7280;
                                                "
                                            >
                                                Order Number
                                            </td>

                                            <td
                                                align="right"
                                                style="
                                                    padding-bottom:18px;
                                                    font-size:15px;
                                                    font-weight:700;
                                                    color:#14532d;
                                                "
                                            >
                                                #{{ $order->id }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td
                                                style="
                                                    padding-bottom:18px;
                                                    font-size:14px;
                                                    color:#6b7280;
                                                "
                                            >
                                                Order Date
                                            </td>

                                            <td
                                                align="right"
                                                style="
                                                    padding-bottom:18px;
                                                    font-size:15px;
                                                    font-weight:600;
                                                    color:#374151;
                                                "
                                            >
                                                {{ $order->created_at->format('d M Y') }}
                                            </td>
                                        </tr>

                                        <tr>
                                            <td
                                                style="
                                                    padding-top:18px;
                                                    border-top:1px solid #dcfce7;
                                                    font-size:14px;
                                                    font-weight:600;
                                                    color:#374151;
                                                "
                                            >
                                                Total Paid
                                            </td>

                                            <td
                                                align="right"
                                                style="
                                                    padding-top:18px;
                                                    border-top:1px solid #dcfce7;
                                                    font-size:24px;
                                                    font-weight:700;
                                                    color:#15803d;
                                                "
                                            >
                                                GHS {{ number_format($order->total_price, 2) }}
                                            </td>
                                        </tr>
                                    </table>

                                </td>
                            </tr>
                        </table>

                        <!-- Status -->
                        <table
                            width="100%"
                            cellpadding="0"
                            cellspacing="0"
                            role="presentation"
                            style="
                                margin-bottom:30px;
                                background:#ecfdf5;
                                border-radius:10px;
                            "
                        >
                            <tr>
                                <td
                                    align="center"
                                    style="
                                        padding:15px;
                                        font-size:14px;
                                        font-weight:600;
                                        color:#166534;
                                    "
                                >
                                    ✓ Payment received and order confirmed
                                </td>
                            </tr>
                        </table>

                        <p
                            style="
                                margin:0;
                                font-size:14px;
                                line-height:1.7;
                                color:#6b7280;
                            "
                        >
                            Please keep this email as confirmation of your payment.
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