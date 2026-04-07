<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Account Linked Successfully</title>
    <style>
        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
            }

            .content-padding {
                padding: 32px 20px !important;
            }
        }
    </style>
</head>

<body
    style="margin:0; padding:0; background-color:#f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation"
        style="background-color:#f8fafc; padding: 40px 10px;">
        <tr>
            <td align="center">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%"
                    style="max-width:540px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">

                    <tr>
                        <td style="height: 4px; background-color: #2b3a55;"></td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 48px 40px;">
                            <div style="text-align: center; margin-bottom: 32px;">
                                <span
                                    style="display: inline-block; background-color: #f1f5f9; padding: 10px 20px; border-radius: 8px; font-weight: 800; color: #2b3a55; font-size: 16px; letter-spacing: 1px;">VENDO</span>
                            </div>

                            <h1
                                style="color: #1e293b; margin: 0 0 16px 0; font-size: 24px; font-weight: 700; line-height: 1.3; text-align: center;">
                                Account Linked Successfully
                            </h1>

                            <div style="color: #475569; font-size: 16px; line-height: 1.6;">
                                <p>Hi {{ $user->first_name }},</p>
                                <p>We're pleased to confirm that your Vendo profile is now securely linked to your
                                    Google account: <span
                                        style="color: #1e293b; font-weight: 600;">{{ $user->email }}</span>.</p>
                                <p>You can now enjoy a faster sign-in experience using your Google credentials whenever
                                    you return to our store.</p>
                            </div>

                            <div style="text-align: center; margin-top: 32px; margin-bottom: 32px;">
                                <a href="https://vendo-ecommerce-two.vercel.app"
                                    style="background-color: #2b3a55; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block; font-size: 15px;">
                                    Start Shopping
                                </a>
                            </div>

                            <table width="100%" border="0" cellpadding="0" cellspacing="0"
                                style="background-color: #f8fafc; border-radius: 8px;">
                                <tr>
                                    <td style="padding: 20px; font-size: 13px; color: #64748b; line-height: 1.5;">
                                        <strong>Security Note:</strong> If you did not authorize this connection, please
                                        secure your account by contacting our support team immediately.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="background-color: #fcfcfc; padding: 32px 40px; text-align: center; border-top: 1px solid #f1f5f9;">
                            <p style="color: #94a3b8; font-size: 12px; line-height: 1.5; margin: 0;">
                                &copy; {{ date('Y') }} Vendo Store. All rights reserved.<br>
                                Phnom Penh, Cambodia
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>