<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #1e293b; line-height: 1.5; }
        .wrapper { max-width: 600px; margin: 20px auto; padding: 40px; border: 1px solid #f1f5f9; border-radius: 12px; }
        .logo { font-size: 28px; font-weight: 800; color: #2b3a55; text-align: center; margin-bottom: 30px; letter-spacing: -1px; }
        .content { font-size: 16px; color: #475569; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #2b3a55; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 20px; }
        .footer { margin-top: 40px; font-size: 12px; color: #94a3b8; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 20px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="logo">VENDO</div>
        
        <div class="content">
            <p>Hi {{ $user->first_name }},</p>
            <p>Welcome to Vendo! We're excited to have you join our community.</p>
            <p>You have successfully linked your Google account (<strong>{{ $user->email }}</strong>) to your Vendo profile. You can now use Google to sign in quickly and securely.</p>
            
            <a href="https://vendo-ecommerce-two.vercel.app" class="btn">Start Shopping</a>
        </div>

        <div class="footer">
            If you didn't create this account, please contact our support immediately.<br>
            &copy; {{ date('Y') }} Vendo Store. All rights reserved.
        </div>
    </div>
</body>
</html>