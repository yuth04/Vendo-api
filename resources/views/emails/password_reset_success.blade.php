<div
    style="background-color: #f1f5f9; padding: 60px 20px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <div
        style="max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">

        <div style="height: 6px; background: linear-gradient(90deg, #059669 0%, #10b981 100%);"></div>

        <div style="padding: 40px 40px 30px;">
            <div style="margin-bottom: 30px; text-align: left;">
                <span style="font-size: 24px; font-weight: 800; letter-spacing: -0.5px; color: #1e293b;">VENDO</span>
            </div>

            <h2
                style="color: #1e293b; font-size: 24px; font-weight: 700; margin: 0 0 16px 0; letter-spacing: -0.025em;">
                Password Reset Successful</h2>

            <p style="color: #475569; font-size: 16px; line-height: 1.6; margin-bottom: 24px;">
                Hello {{ $user->name }},<br><br>
                This email confirms that your password for your <strong>Vendo</strong> account has been successfully
                updated. Your account security is our priority.
            </p>

            <div
                style="background-color: #f0fdf4; border: 1px solid #dcfce7; border-radius: 8px; padding: 12px 16px; display: inline-flex; align-items: center; margin-bottom: 32px;">
                <svg width="20" height="20" fill="#16a34a" viewBox="0 0 20 20" style="margin-right: 10px;">
                    <path fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd"></path>
                </svg>
                <span style="color: #166534; font-size: 14px; font-weight: 600;">Account status: Secured</span>
            </div>

            <div style="text-align: left; margin-bottom: 40px;">
                <a href="{{ url('https://vendo-ecommerce-two.vercel.app/') }}"
                    style="background-color: #0f172a; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; display: inline-block; font-size: 15px;">
                    Continue to Dashboard
                </a>
            </div>

            <div style="border-top: 1px solid #f1f5f9; padding-top: 24px;">
                <p style="color: #64748b; font-size: 14px; line-height: 1.5; margin: 0;">
                    <strong>Didn't make this change?</strong><br>
                    If you did not authorize this password update, please <a href="#"
                        style="color: #10b981; text-decoration: underline;">secure your account immediately</a> or
                    contact our security team.
                </p>
            </div>
        </div>

        <div style="background-color: #f8fafc; padding: 30px 40px; text-align: left; border-top: 1px solid #f1f5f9;">
            <p style="color: #94a3b8; font-size: 12px; line-height: 1.5; margin: 0;">
                &copy; {{ date('Y') }} Vendo E-commerce. All rights reserved.<br>
                Phnom Penh, Cambodia
            </p>
        </div>
    </div>
</div>