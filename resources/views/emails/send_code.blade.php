<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Code</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f7fa; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f7fa; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="100%" max-width="500" cellpadding="0" cellspacing="0" style="max-width:500px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">
                    
                    <tr>
                        <td style="height: 4px; background-color: #0f172a;"></td>
                    </tr>

                    <tr>
                        <td style="padding: 40px 40px 20px; text-align: left;">
                            <div style="font-size: 20px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; margin-bottom: 25px;">VENDO</div>
                            <h2 style="margin:0; color:#1e293b; font-size:24px; font-weight: 700; letter-spacing: -0.025em;">Confirm your email address</h2>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 30px; text-align: left;">
                            <p style="color:#475569; font-size:16px; line-height:1.6; margin-bottom: 30px;">
                                Thank you for choosing Vendo. Please use the following security code to verify your identity and complete your account setup.
                            </p>

                            <div style="background-color:#f8fafc; border: 1px solid #e2e8f0; border-radius:12px; padding: 30px; text-align: center; margin-bottom: 30px;">
                                <p style="margin: 0 0 10px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em; color: #64748b; font-weight: 600;">Verification Code</p>
                                <span style="font-family: 'Courier New', Courier, monospace; font-size:38px; font-weight: 800; letter-spacing: 12px; color:#0f172a; display: block;">
                                    {{ $code ?? '123456' }}
                                </span>
                            </div>

                            <p style="color:#94a3b8; font-size:13px; line-height: 1.5;">
                                This security code is valid for <span style="color:#ef4444; font-weight: 600;">{{ $expire ?? 10 }} minutes</span>. 
                                For your security, do not share this code with anyone.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px; text-align: left;">
                            <div style="border-top: 1px solid #f1f5f9; padding-top: 25px;">
                                <p style="color:#64748b; font-size:13px; margin: 0;">
                                    If you didn't request this, you can safely ignore this email. Someone may have entered your email address by mistake.
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #f8fafc; padding: 30px 40px; text-align: left;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="color:#94a3b8; font-size:12px; line-height: 1.6;">
                                        <strong>Vendo Team</strong><br>
                                        Phnom Penh, Cambodia<br>
                                        <a href="#" style="color: #64748b; text-decoration: underline;">Support</a> &nbsp;•&nbsp; <a href="#" style="color: #64748b; text-decoration: underline;">Privacy Policy</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>