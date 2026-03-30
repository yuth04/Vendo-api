<div style="background-color: #f8fafc; padding: 50px 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <div style="max-width: 500px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
        
        <div style="background: linear-gradient(135deg, #2b3a55 0%, #4a5d7e 100%); padding: 40px 20px; text-align: center;">
            <div style="background: rgba(255,255,255,0.2); width: 60px; height: 60px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center;">
                <span style="font-size: 30px;">🔄</span>
            </div>
            <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: 600;">Return Request Update</h1>
        </div>

        <div style="padding: 35px; color: #333333; line-height: 1.6;">

            <p style="color: #1e293b; font-size: 17px; font-weight: 600; margin-bottom: 10px;">
                Hello {{ $returnOrder->user->first_name }},
            </p>

            <p style="color: #64748b; font-size: 15px; margin-bottom: 20px;">
                There has been an update regarding your return request for 
                <strong style="color: #1e293b;">Order #{{ $returnOrder->order->order_number }}</strong>.
            </p>

            <div style="text-align: center; margin: 30px 0;">
                <span style="
                    display: inline-block;
                    padding: 8px 24px;
                    border-radius: 50px;
                    font-size: 14px;
                    font-weight: 700;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    background: {{ $returnOrder->status === 'approved' ? '#dcfce7' : '#fee2e2' }};
                    color: {{ $returnOrder->status === 'approved' ? '#166534' : '#991b1b' }};
                    border: 1px solid {{ $returnOrder->status === 'approved' ? '#bbf7d0' : '#fecaca' }};
                ">
                    Status: {{ $returnOrder->status }}
                </span>
            </div>

            @if($returnOrder->status === 'approved')
                <div style="padding: 20px; background: #f0f9ff; border-radius: 12px; border-left: 4px solid #0ea5e9;">
                    <p style="margin: 0; color: #0369a1; font-size: 14px; font-weight: 500;">
                        <strong>Next Steps:</strong> Please log in to your Vendo dashboard to download your return label and follow the shipping instructions.
                    </p>
                </div>
                
                <div style="text-align: center; margin-top: 25px;">
                    <a href="{{ url('/dashboard/returns') }}" style="background-color: #2b3a55; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 10px; font-weight: 600; display: inline-block; font-size: 14px;">
                        View Instructions
                    </a>
                </div>
            @else
                <div style="padding: 20px; background: #fef2f2; border-radius: 12px; border-left: 4px solid #ef4444;">
                    <p style="margin: 0; color: #991b1b; font-size: 14px; font-weight: 500;">
                        Unfortunately, your request could not be approved at this time. If you have questions, please reach out to our support team.
                    </p>
                </div>
            @endif

            <p style="margin-top: 35px; text-align: center; color: #94a3b8; font-size: 14px;">
                Thank you for shopping with <strong>Vendo</strong>.
            </p>
        </div>

        <div style="padding: 0 35px 30px 35px; text-align: center;">
            <div style="border-top: 1px solid #e2e8f0; padding-top: 20px; color: #cbd5e1; font-size: 11px;">
                © {{ date('Y') }} Vendo E-commerce<br>
                Phnom Penh, Cambodia
            </div>
        </div>

    </div>
</div>