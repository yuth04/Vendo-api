<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Welcome Back to Vendo</title>
    <style>
        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }
        @media screen and (max-width: 600px) {
            .container { width: 100% !important; }
            .content-spacing { padding: 32px 20px !important; }
            .headline { font-size: 26px !important; }
        }
    </style>
</head>
<body style="margin:0; padding:0; width:100%; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; background-color:#f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">

    <div style="background-color: #f8fafc;">
        <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f8fafc; padding: 40px 10px;">
            <tr>
                <td align="center">
                    <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation" style="max-width:540px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);">
                        
                        <tr>
                            <td style="height: 5px; background-color: #2b3a55; background: linear-gradient(90deg, #2b3a55 0%, #4a5d7e 100%);"></td>
                        </tr>

                        <tr>
                            <td class="content-spacing" style="padding: 48px 40px; text-align: center;">
                                
                                <div style="margin-bottom: 24px;">
                                    <span style="display: inline-block; background-color: #f1f5f9; padding: 10px 24px; border-radius: 8px; font-weight: 700; color: #2b3a55; font-size: 16px; letter-spacing: 2px; text-transform: uppercase;">VENDO</span>
                                </div>

                                <h1 class="headline" style="color: #1e293b; margin: 0 0 16px 0; font-size: 30px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.2;">
                                    Welcome back, {{ $user->first_name }}
                                </h1>

                                <p style="color: #475569; font-size: 16px; line-height: 1.6; margin: 0 auto 32px auto; max-width: 420px;">
                                    We’re delighted to have you back in the community. Your account is active and our latest seasonal curated collections are now live.
                                </p>

                                <div style="margin-bottom: 40px;">
                                    <a href="https://vendo-ecommerce-two.vercel.app/" 
                                       style="background-color: #2b3a55; color: #ffffff; padding: 16px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block; font-size: 15px;">
                                        Explore New Arrivals
                                    </a>
                                </div>

                                <hr style="border: 0; border-top: 1px solid #f1f5f9; margin-bottom: 32px;">

                                <table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
                                    <tr>
                                        <td style="text-align: left; background-color: #f8fafc; border-left: 4px solid #2b3a55; border-radius: 4px; padding: 20px;">
                                            <p style="margin: 0; color: #1e293b; font-size: 14px; font-weight: 600;">Account Update</p>
                                            <p style="margin: 4px 0 0 0; color: #64748b; font-size: 13px; line-height: 1.5;">Your wishlist is being synced with current inventory and updated pricing.</p>
                                        </td>
                                    </tr>
                                </table>

                            </td>
                        </tr>

                        <tr>
                            <td style="background-color: #fcfcfc; padding: 32px 40px; text-align: center; border-top: 1px solid #f1f5f9;">
                                <p style="color: #94a3b8; font-size: 12px; line-height: 1.8; margin: 0; font-weight: 500;">
                                    <strong>The Vendo Team</strong><br>
                                    Phnom Penh, Cambodia<br><br>
                                    <a href="#" style="color: #64748b; text-decoration: underline;">Privacy Policy</a> &nbsp;&bull;&nbsp; 
                                    <a href="#" style="color: #64748b; text-decoration: underline;">Manage Preferences</a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        </div>
</body>
</html>