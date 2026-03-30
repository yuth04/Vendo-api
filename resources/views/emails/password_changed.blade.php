<div style="background-color: #f8fafc; padding: 50px 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <div style="max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
        
        <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 40px 20px; text-align: center;">
            <div style="background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 30px;">✨</span>
            </div>
            <h2 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 600;">Success!</h2>
        </div>

        <div style="padding: 40px 35px; text-align: center;">
            <h3 style="color: #1e293b; font-size: 19px; margin: 0 0 10px 0;">Password Updated</h3>
            
            <p style="color: #64748b; font-size: 15px; line-height: 1.7; margin-bottom: 25px;">
                Hi {{ $user->name }}, your <strong>Vendo</strong> account password has been changed successfully. You can now use your new password to log in.
            </p>

            <div style="display: inline-block; margin-bottom: 30px;">
                <div style="background-color: #ecfdf5; color: #10b981; padding: 10px 20px; border-radius: 50px; font-size: 14px; font-weight: 700; display: flex; align-items: center; border: 1px solid #d1fae5;">
                    <span style="margin-right: 8px;">✓</span> Account Secure
                </div>
            </div>

            <div style="margin-top: 10px;">
                <a href="{{ url('https://vendo-ecommerce-two.vercel.app/') }}" style="background-color: #2b3a55; color: #ffffff; padding: 14px 40px; text-decoration: none; border-radius: 10px; font-weight: 600; display: inline-block; font-size: 15px; box-shadow: 0 4px 12px rgba(43, 58, 85, 0.15);">
                    Back to Shopping
                </a>
            </div>
            
            <p style="color: #94a3b8; font-size: 13px; margin-top: 30px;">
                If you didn't make this change, please contact our support immediately.
            </p>
        </div>

        <div style="padding: 0 35px 35px 35px; text-align: center;">
            <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; color: #cbd5e1; font-size: 12px;">
                Sent with ❤️ from Vendo Team<br>
                Phnom Penh, Cambodia
            </div>
        </div>
    </div>
</div>