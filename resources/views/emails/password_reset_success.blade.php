<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset Successful</title>
</head>
<body style="margin:0; padding:20px; font-family:Arial,sans-serif; background-color:#f8f9fa;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#fff; border-radius:8px; padding:30px; box-shadow:0 4px 10px rgba(0,0,0,0.05);">
                    <tr>
                        <td style="text-align:center; padding-bottom:20px;">
                            <h2 style="color:#0d6efd; font-size:24px; margin:0;">Password Reset Successful</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:16px; color:#212529; line-height:1.5; text-align:center;">
                            <p>Hello {{ $name }},</p>
                            <p>Your password has been successfully reset. You can now log in using your new password.</p>
                            <p>If you did not perform this action, please contact our support immediately.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top:20px; border-top:1px solid #dee2e6; font-size:14px; color:#6c757d; text-align:center;">
                            Thanks,<br>
                            {{ config('app.name') }} Team
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>