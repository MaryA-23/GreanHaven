<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenHaven Admin Account Created</title>
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
                                font-size:28px;
                                font-weight:700;
                                margin-bottom:6px;
                            "
                        >
                            GreenHaven
                        </div>

                        <div
                            style="
                                font-size:14px;
                                color:#bbf7d0;
                            "
                        >
                            Admin Portal
                        </div>
                    </td>
                </tr>

                <!-- Content -->
                <tr>
                    <td style="padding:40px 35px;">

                        <h2
                            style="
                                margin:0 0 15px;
                                font-size:24px;
                                color:#14532d;
                            "
                        >
                            Welcome, {{ $mail_details['name'] }}
                        </h2>

                        <p
                            style="
                                font-size:16px;
                                line-height:1.7;
                                margin-bottom:25px;
                                color:#4b5563;
                            "
                        >
                            Your GreenHaven administrator account has been created successfully.
                        </p>

                        <!-- Account Details -->
                        <table
                            width="100%"
                            cellpadding="0"
                            cellspacing="0"
                            style="
                                background:#f0fdf4;
                                border:1px solid #dcfce7;
                                border-radius:12px;
                                margin-bottom:30px;
                            "
                        >
                            <tr>
                                <td style="padding:20px;">

                                    <div
                                        style="
                                            font-size:13px;
                                            color:#6b7280;
                                            margin-bottom:5px;
                                        "
                                    >
                                        EMAIL ADDRESS
                                    </div>

                                    <div
                                        style="
                                            font-size:16px;
                                            font-weight:600;
                                            margin-bottom:20px;
                                        "
                                    >
                                        {{ $mail_details['email'] }}
                                    </div>

                                    <div
                                        style="
                                            font-size:13px;
                                            color:#6b7280;
                                            margin-bottom:5px;
                                        "
                                    >
                                        TEMPORARY PASSWORD
                                    </div>

                                    <div
                                        style="
                                            font-size:18px;
                                            font-weight:700;
                                            color:#14532d;
                                        "
                                    >
                                        {{ $mail_details['password'] }}
                                    </div>

                                </td>
                            </tr>
                        </table>

                        <!-- Button -->
                        <div style="text-align:center; margin:30px 0;">

                            <a
                                href="{{ $mail_details['url'] }}"
                                style="
                                    display:inline-block;
                                    background:#16a34a;
                                    color:#ffffff;
                                    text-decoration:none;
                                    padding:14px 32px;
                                    border-radius:10px;
                                    font-weight:600;
                                    font-size:15px;
                                "
                            >
                                Login to Admin Portal
                            </a>

                        </div>

                        <p
                            style="
                                font-size:14px;
                                line-height:1.7;
                                color:#6b7280;
                            "
                        >
                            For security, please change your temporary password after your first login.
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