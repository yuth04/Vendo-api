<div style="background-color: #f8fafc; padding: 40px 20px; font-family: sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0;">
        
        <div style="background-color: #2b3a55; padding: 30px; text-align: center;">
            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">Welcome to Vendo!</h1>
        </div>

        <div style="padding: 40px; text-align: center;">
            <h2 style="color: #1e293b;">Hi {{ $user->first_name }},</h2>
            <p style="color: #64748b; font-size: 16px; line-height: 1.6;">
                Your email has been successfully verified. We are so excited to have you join our community! 
                You can now start shopping for your favorite items.
            </p>

            <div style="margin-top: 30px;">
                <a href="{{ url('https://vendo-ecommerce-two.vercel.app/') }}" style="background-color: #2b3a55; color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: bold;">Start Shopping</a>
            </div>
        </div>

        <div style="background-color: #f1f5f9; padding: 20px; text-align: center; color: #94a3b8; font-size: 12px;">
            <p>© {{ date('Y') }} Vendo E-commerce. Happy Shopping!</p>
        </div>
    </div>
</div>