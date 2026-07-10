<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Role Updated</title>
</head>
<body>

<h2>Hello {{ $mail_details['name'] }},</h2>

<p>Your role on the GreenHaven Admin Portal has been updated.</p>

<p><strong>New Role:</strong> {{ $mail_details['role'] }}</p>

<p>If you were not expecting this change, please contact the system administrator.</p>

<p>Regards,<br>GreenHaven Team</p>

</body>
</html>