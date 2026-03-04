<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verification Code</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">

                <!-- Email Container -->
                <table width="500" cellpadding="0" cellspacing="0" 
                       style="background:#ffffff; padding:30px; border-radius:10px; box-shadow:0 5px 15px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <!-- <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <h2 style="margin:0; color:#333;">
                                {{ config('app.name') }}
                            </h2>
                        </td>
                    </tr> -->

                    <!-- Title -->
                    <tr>
                        <td align="center">
                            <h1 style="margin:0; color:#222;">
                                Verify Your Email
                            </h1>
                        </td>
                    </tr>

                    <!-- Message -->
                    <tr>
                        <td style="padding:20px 0; color:#555; font-size:15px; text-align:center;">
                            Use the verification code below to continue.
                            This code will expire in <strong>10 minutes</strong>.
                        </td>
                    </tr>

                    <!-- OTP Code -->
                    <tr>
                        <td align="center" style="padding:20px 0;">
                            <div style="
                                display:inline-block;
                                padding:15px 30px;
                                font-size:28px;
                                letter-spacing:6px;
                                font-weight:bold;
                                color:#ffffff;
                                background:#4CAF50;
                                border-radius:8px;">
                                {{ $code ?? '123456' }}
                            </div>
                        </td>
                    </tr>

                    <!-- Footer Text -->
                    <tr>
                        <td style="padding-top:20px; font-size:13px; color:#999; text-align:center;">
                            If you did not request this code, you can safely ignore this email.
                        </td>
                    </tr>

                </table>

                <!-- Bottom Footer -->
                <p style="margin-top:20px; font-size:12px; color:#aaa;">
                    © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>

            </td>
        </tr>
    </table>

</body>
</html>