<div style="background-color: #f9fafb; padding: 50px 20px; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
        
        <div style="padding: 40px 40px 20px; text-align: center;">
            <div style="display: inline-block; background-color: #f1f5f9; padding: 12px 24px; border-radius: 12px;">
                <span style="font-size: 22px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; text-transform: uppercase;">Vendo</span>
            </div>
        </div>

        <div style="padding: 0 40px 40px; text-align: center;">
            <h1 style="color: #111827; font-size: 26px; font-weight: 700; margin-bottom: 16px; letter-spacing: -0.025em;">Verification Successful</h1>
            
            <p style="color: #4b5563; font-size: 16px; line-height: 1.6; margin-bottom: 32px;">
                Hi {{ $user->first_name }}, your email has been verified. Welcome to the community! Your account is now fully active, and you're ready to explore everything Vendo has to offer.
            </p>

            <div style="margin-bottom: 40px;">
                <a href="{{ url('https://vendo-ecommerce-two.vercel.app/') }}" style="background-color: #0f172a; color: #ffffff; padding: 16px 36px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; display: inline-block; transition: background-color 0.2s;">
                    Explore the Marketplace
                </a>
            </div>

            <div style="border-top: 1px solid #f3f4f6; padding-top: 30px; display: flex; justify-content: center; gap: 20px;">
                <div style="text-align: center; flex: 1;">
                    <div style="font-weight: 600; color: #111827; font-size: 14px;">Fast Shipping</div>
                    <div style="color: #9ca3af; font-size: 12px;">Doorstep delivery</div>
                </div>
                <div style="text-align: center; flex: 1;">
                    <div style="font-weight: 600; color: #111827; font-size: 14px;">Secure Pay</div>
                    <div style="color: #9ca3af; font-size: 12px;">100% Protected</div>
                </div>
                <div style="text-align: center; flex: 1;">
                    <div style="font-weight: 600; color: #111827; font-size: 14px;">24/7 Support</div>
                    <div style="color: #9ca3af; font-size: 12px;">We're here to help</div>
                </div>
            </div>
        </div>

        <div style="background-color: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #f1f5f9;">
            <p style="color: #64748b; font-size: 13px; margin: 0 0 10px;">
                You're receiving this because you signed up for Vendo.
            </p>
            <div style="color: #94a3b8; font-size: 12px;">
                © {{ date('Y') }} Vendo E-commerce &bull; Phnom Penh, Cambodia
            </div>
        </div>
    </div>
</div>