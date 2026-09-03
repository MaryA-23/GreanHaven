<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Welcome to GreenHaven</title>
</head>

<body style="
    margin: 0;
    padding: 0;
    background-color: #f4f7f5;
    font-family: Arial, Helvetica, sans-serif;
    color: #1f2937;
">

<table width="100%" cellpadding="0" cellspacing="0" style="padding: 40px 15px;">
    <tr>
        <td align="center">

            <table
                width="100%"
                cellpadding="0"
                cellspacing="0"
                style="
                    max-width: 600px;
                    background-color: #ffffff;
                    border-radius: 16px;
                    overflow: hidden;
                    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
                "
            >

                <!-- HEADER -->
                <tr>
                    <td
                        style="
                            background-color: #166534;
                            padding: 35px 30px;
                            text-align: center;
                        "
                    >

                        <h1
                            style="
                                margin: 0;
                                color: #ffffff;
                                font-size: 30px;
                                font-weight: 700;
                            "
                        >
                            🌿 GreenHaven
                        </h1>

                        <p
                            style="
                                margin: 10px 0 0;
                                color: #dcfce7;
                                font-size: 15px;
                            "
                        >
                            Fresh produce. Better living.
                        </p>

                    </td>
                </tr>


                <!-- CONTENT -->
                <tr>
                    <td style="padding: 45px 40px;">

                        <h2
                            style="
                                margin: 0 0 20px;
                                font-size: 26px;
                                color: #111827;
                            "
                        >
                            Welcome,
                            {{ $user->first_name ?? $user->name ?? 'Friend' }}!
                        </h2>


                        <p
                            style="
                                font-size: 16px;
                                line-height: 1.7;
                                color: #4b5563;
                                margin-bottom: 20px;
                            "
                        >
                            Thank you for creating your GreenHaven account.
                        </p>


                        <p
                            style="
                                font-size: 16px;
                                line-height: 1.7;
                                color: #4b5563;
                                margin-bottom: 30px;
                            "
                        >
                            Before you start shopping for fresh farm produce,
                            please verify your email address by clicking the button below.
                        </p>


                        <!-- VERIFY BUTTON -->
                        <div style="text-align: center; margin: 35px 0;">

                            <a
                                href="{{ $verifyUrl }}"
                                style="
                                    display: inline-block;
                                    background-color: #15803d;
                                    color: #ffffff;
                                    text-decoration: none;
                                    padding: 15px 30px;
                                    border-radius: 8px;
                                    font-size: 16px;
                                    font-weight: 700;
                                "
                            >
                                Verify Email Address
                            </a>

                        </div>


                        <!-- INFO BOX -->
                        <div
                            style="
                                background-color: #f0fdf4;
                                border: 1px solid #bbf7d0;
                                border-radius: 10px;
                                padding: 20px;
                                margin-top: 30px;
                            "
                        >

                            <p
                                style="
                                    margin: 0;
                                    font-size: 14px;
                                    line-height: 1.6;
                                    color: #166534;
                                "
                            >
                                After verifying your email, you will be able to sign in
                                and start shopping with GreenHaven.
                            </p>

                        </div>


                        <p
                            style="
                                margin-top: 35px;
                                font-size: 15px;
                                line-height: 1.7;
                                color: #6b7280;
                            "
                        >
                            If you did not create this account,
                            you can safely ignore this email.
                        </p>


                        <p
                            style="
                                margin-top: 30px;
                                font-size: 15px;
                                color: #374151;
                            "
                        >
                            Kind regards,<br>
                            <strong>GreenHaven Team</strong>
                        </p>

                    </td>
                </tr>


                <!-- FOOTER -->
                <tr>
                    <td
                        style="
                            background-color: #f9fafb;
                            padding: 25px 30px;
                            text-align: center;
                            border-top: 1px solid #e5e7eb;
                        "
                    >

                        <p
                            style="
                                margin: 0;
                                font-size: 13px;
                                color: #9ca3af;
                            "
                        >
                            © {{ date('Y') }} GreenHaven. All rights reserved.
                        </p>

                        <p
                            style="
                                margin: 8px 0 0;
                                font-size: 13px;
                                color: #9ca3af;
                            "
                        >
                            Fresh from farm to table.
                        </p>

                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>