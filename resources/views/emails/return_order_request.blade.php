<div style="background: linear-gradient(135deg, #eef2ff, #f8fafc); padding: 50px 15px; font-family: 'Inter', system-ui, sans-serif;">
    
    <div style="max-width: 620px; margin: auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;">
        
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #1e293b, #0f172a); padding: 35px; text-align: center;">
            <div style="font-size: 13px; font-weight: 700; color: #a5b4fc; letter-spacing: 1px;">RETURN UPDATE</div>
            <h2 style="margin: 10px 0 5px; color: #ffffff; font-size: 26px; font-weight: 800;">
                Your Request Status Changed
            </h2>
            <p style="margin: 0; color: #94a3b8; font-size: 13px;">
                Order #{{ $returnOrder->order_id }}
            </p>
        </div>

        <!-- Body -->
        <div style="padding: 35px;">

            <!-- Status Badge -->
            <div style="text-align: center; margin-bottom: 30px;">
                <span style="
                    display: inline-block;
                    padding: 10px 24px;
                    border-radius: 999px;
                    font-size: 14px;
                    font-weight: 800;
                    letter-spacing: 0.5px;
                    background: 
                        {{ $returnOrder->status == 'approved' ? '#dcfce7' : 
                           ($returnOrder->status == 'rejected' ? '#fee2e2' : '#fef9c3') }};
                    color:
                        {{ $returnOrder->status == 'approved' ? '#166534' : 
                           ($returnOrder->status == 'rejected' ? '#991b1b' : '#854d0e') }};
                ">
                    {{ strtoupper($returnOrder->status) }}
                </span>
            </div>

            <!-- Refund -->
            @if($returnOrder->refund_amount > 0)
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 20px; margin-bottom: 25px;">
                <p style="margin: 0; font-size: 13px; color: #166534; font-weight: 600;">
                    Approved Refund Amount
                </p>
                <h3 style="margin: 8px 0 0; color: #059669; font-size: 26px;">
                    ${{ number_format($returnOrder->refund_amount, 2) }}
                </h3>
            </div>
            @endif

            <!-- Message -->
            <div style="margin-bottom: 30px;">
                <h4 style="margin-bottom: 10px; font-size: 14px; color: #334155; font-weight: 700;">
                    Message from Support
                </h4>
                <div style="background: #f8fafc; border-left: 4px solid #6366f1; padding: 18px; border-radius: 8px; font-size: 14px; color: #475569; line-height: 1.6;">
                    "{{ $returnOrder->message ?? 'Your return request is currently under review. We will update you shortly with the next steps.' }}"
                </div>
            </div>

            <!-- Timeline -->
            <div style="margin-bottom: 35px;">
                <h4 style="font-size: 14px; margin-bottom: 12px; color: #1e293b;">Progress</h4>
                <div style="height: 6px; background: #e2e8f0; border-radius: 10px;">
                    <div style="
                        width: 
                        {{ $returnOrder->status == 'pending' ? '30%' : 
                           ($returnOrder->status == 'approved' ? '100%' : 
                           ($returnOrder->status == 'rejected' ? '100%' : '60%')) }};
                        height: 100%;
                        background: #6366f1;
                        border-radius: 10px;
                    "></div>
                </div>
            </div>

            <!-- CTA -->
            <div style="text-align: center;">
                <a href="{{ url('/orders/' . $returnOrder->order_id) }}"
                   style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #3b82f6, #6366f1); color: white; text-decoration: none; font-weight: 700; border-radius: 10px; font-size: 15px; box-shadow: 0 10px 15px -3px rgba(99,102,241,0.3);">
                    View Return Details
                </a>
            </div>

        </div>

        <!-- Footer -->
        <div style="background: #f1f5f9; padding: 25px; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="margin: 0; font-size: 12px; color: #64748b;">
                © {{ date('Y') }} Vendor Management System
            </p>
            <p style="margin-top: 8px; font-size: 11px; color: #94a3b8;">
                This email was sent automatically. Please do not reply.
            </p>
        </div>

    </div>
</div>