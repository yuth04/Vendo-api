<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Password Reset Successful</title>
</head>
<body style="margin:0; padding:50px 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color:#f8fafc;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="500" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                    
                    <tr>
                        <td style="background: linear-gradient(135deg, #2b3a55 0%, #4a5d7e 100%); padding: 40px 20px; text-align: center;">
                            <div style="background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 30px;">✅</span>
                            </div>
                            <h2 style="color:#ffffff; font-size:24px; font-weight: 600; margin:0;">Password Updated!</h2>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 35px; text-align:center;">
                            <p style="color:#1e293b; font-size:18px; font-weight: 600; margin-bottom: 10px;">Hello {{ $name }},</p>
                            <p style="color:#64748b; font-size:15px; line-height:1.6;">Your password has been successfully reset. You can now use your new credentials to log back into your <strong>Vendo</strong> account.</p>
                            
                            <div style="margin-top: 25px;">
                                <a href="{{ url('/login') }}" style="background-color:#2b3a55; color:#ffffff; padding: 14px 30px; text-decoration:none; border-radius:10px; font-weight:600; display:inline-block; font-size:15px;">Login to Your Account</a>
                            </div>

                            <p style="color:#94a3b8; font-size:13px; margin-top: 30px; line-height:1.5;">
                                If you did not perform this action, please contact our support team immediately to secure your account.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 35px 35px 35px; text-align:center;">
                            <div style="border-top:1px solid #e2e8f0; padding-top:20px; color:#cbd5e1; font-size:12px;">
                                Sent with ❤️ from Vendo Team<br>
                                Phnom Penh, Cambodia
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>