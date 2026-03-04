<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verification Code</title>
</head>
<body style="margin:0; padding:20px; background-color:#f8f9fa; font-family: Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" 
                       style="background-color:#ffffff; border-radius:8px; padding:30px; box-shadow:0 4px 10px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr>
                        <td style="text-align:center; padding-bottom:20px;">
                            <h2 style="margin:0; color:#0d6efd; font-size:24px;">Verify Your Email</h2>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="color:#212529; font-size:16px; line-height:1.5;">
                            <p>Hello,</p>
                            <p>You recently requested a verification code. Use the code below to verify your email:</p>

                            <!-- OTP Code -->
                            <p style="text-align:center; margin:30px 0;">
                                <span style="
                                    display:inline-block;
                                    padding:15px 25px;
                                    font-size:28px;
                                    font-weight:bold;
                                    letter-spacing:5px;
                                    color:#ffffff;
                                    background-color:#0d6efd;
                                    border-radius:5px;
                                ">
                                    {{ $code ?? '123456' }}
                                </span>
                            </p>

                            <p>This code will expire in <strong>{{ $expire ?? 10 }} minutes</strong>.</p>
                            <p>If you did not request this code, you can safely ignore this email.</p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding-top:20px; border-top:1px solid #dee2e6; color:#6c757d; font-size:14px; text-align:center;">
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