<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Account Created</title>
</head>
<body>

    <h2>Hello {{ $mail_details['name'] }},</h2>

    <p>Your Greenhaven administrator account has been created successfully.</p>

    <p><strong>Email:</strong> {{ $mail_details['email'] }}</p>

    <p><strong>Temporary Password:</strong> {{ $mail_details['password'] }}</p>

    <p>
        Login here:
        <a href="{{ $mail_details['url'] }}">
            {{ $mail_details['url'] }}
        </a>
    </p>

    <p>Please change your password after your first login.</p>

    <p>Regards,<br>Greenhaven Team</p>

</body>
</html>