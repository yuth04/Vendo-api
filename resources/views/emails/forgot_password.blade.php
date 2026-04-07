<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Reset Your Password - Vendo</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
            }

            .content-padding {
                padding: 30px 20px !important;
            }
        }
    </style>
</head>

<body style="background-color: #f8fafc; padding: 60px 10px;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
        <tr>
            <td align="center">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%"
                    style="max-width: 500px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">

                    <tr>
                        <td style="height: 4px; background-color: #2b3a55;"></td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 48px 40px 32px; text-align: center;">
                            <div style="margin-bottom: 25px;">
                                <span
                                    style="display: inline-block; background-color: #f1f5f9; padding: 12px 20px; border-radius: 12px; font-weight: 800; color: #2b3a55; font-size: 18px; letter-spacing: 1px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">VENDO</span>
                            </div>
                            <h1
                                style="margin: 0; color: #1e293b; font-size: 24px; font-weight: 800; letter-spacing: -0.02em;">
                                Password Reset</h1>
                            <p style="margin: 12px 0 0; color: #64748b; font-size: 15px; line-height: 1.6;">
                                We received a request to reset your Vendo account password. Use the verification code
                                below to proceed.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px;">
                            <div
                                style="background-color: #f1f5f9; border-radius: 12px; padding: 32px 20px; text-align: center; border: 1px solid #e2e8f0;">
                                <div
                                    style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 16px;">
                                    Verification Code</div>
                                <div
                                    style="font-family: ui-monospace, 'Cascadia Code', 'Source Code Pro', Menlo, Consolas, monospace; font-size: 36px; font-weight: 800; color: #2b3a55; letter-spacing: 12px; margin-left: 12px;">
                                    {{ $code }}
                                </div>
                            </div>

                            <div style="margin-top: 24px; text-align: center;">
                                <p style="margin: 0; font-size: 13px; color: #64748b;">
                                    This code is valid for <strong style="color: #ef4444;">{{ $expired }}
                                        minutes</strong>.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding: 32px 40px; background-color: #fcfcfc; border-top: 1px solid #f1f5f9; text-align: center;">
                            <p style="margin: 0; color: #94a3b8; font-size: 13px; line-height: 1.6;">
                                <strong>Didn't request this?</strong><br>
                                You can safely ignore this email. Your password will remain unchanged.
                            </p>
                        </td>
                    </tr>
                </table>

                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 500px;">
                    <tr>
                        <td style="padding: 30px; text-align: center;">
                            <p
                                style="margin: 0; color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                Sent with ❤️ from Vendo Team
                            </p>
                            <p style="margin: 6px 0 0; color: #cbd5e1; font-size: 11px;">
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