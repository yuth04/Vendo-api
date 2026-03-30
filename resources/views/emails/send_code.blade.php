<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Verification Code</title>
</head>
<body style="margin:0; padding:50px 20px; background-color:#f8fafc; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="500" cellpadding="0" cellspacing="0" 
                       style="background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">

                    <tr>
                        <td style="background: linear-gradient(135deg, #2b3a55 0%, #4a5d7e 100%); padding: 40px 20px; text-align: center;">
                            <div style="background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                                <span style="font-size: 30px;">📩</span>
                            </div>
                            <h2 style="margin:0; color:#ffffff; font-size:24px; font-weight: 600;">Verify Your Email</h2>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 35px; text-align: center;">
                            <p style="color:#1e293b; font-size:18px; font-weight: 600; margin-bottom: 10px;">Almost there!</p>
                            <p style="color:#64748b; font-size:15px; line-height:1.6;">Use the verification code below to confirm your email address and finish setting up your <strong>Vendo</strong> account.</p>

                            <div style="margin:30px 0;">
                                <span style="
                                    display:inline-block;
                                    padding:16px 35px;
                                    font-size:32px;
                                    font-weight:bold;
                                    letter-spacing:10px;
                                    color:#2b3a55;
                                    background-color:#f1f5f9;
                                    border-radius:12px;
                                    border: 2px dashed #cbd5e1;
                                ">
                                    {{ $code ?? '123456' }}
                                </span>
                            </div>

                            <p style="color:#94a3b8; font-size:13px;">This code will expire in <strong style="color:#ef4444;">{{ $expire ?? 10 }} minutes</strong>.</p>
                            <p style="color:#64748b; font-size:14px; margin-top: 25px;">If you did not request this code, you can safely ignore this email.</p>
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