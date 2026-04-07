<div style="background-color: #f1f5f9; padding: 40px 10px; font-family: 'Inter', system-ui, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;">
        
        <div style="background-color: #1e293b; padding: 30px; text-align: center;">
            <span style="background: #6366f1; color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">System Update</span>
            <h2 style="margin: 10px 0 0; color: #ffffff; font-size: 24px; font-weight: 700;">Return Request Updated</h2>
            <p style="margin: 5px 0 0; color: #94a3b8; font-size: 14px;">Order Reference: #{{ $returnOrder->order_id }}</p>
        </div>

        <div style="padding: 30px;">
            
            <div style="display: flex; align-items: center; justify-content: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 25px; margin-bottom: 25px; text-align: center;">
                <div style="width: 100%;">
                    <p style="margin: 0 0 8px; color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">New Request Status</p>
                    <span style="display: inline-block; background-color: #dbeafe; color: #1e40af; padding: 8px 20px; border-radius: 8px; font-size: 18px; font-weight: 800; text-transform: uppercase;">
                        {{ $returnOrder->status }}
                    </span>
                </div>
            </div>

            @if($returnOrder->refund_amount > 0)
            <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-left: 4px solid #10b981; border-radius: 8px; padding: 20px; margin-bottom: 25px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>
                            <p style="margin: 0; color: #64748b; font-size: 13px; font-weight: 600;">Approved Refund</p>
                        </td>
                        <td align="right">
                            <p style="margin: 0; color: #059669; font-size: 22px; font-weight: 800;">
                                ${{ number_format($returnOrder->refund_amount, 2) }}
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            @endif

            <div style="margin-bottom: 35px;">
                <h4 style="margin: 0 0 12px; color: #1e293b; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">Official Note:</h4>
                <div style="background: #f8fafc; border-radius: 8px; padding: 20px; color: #334155; font-size: 15px; line-height: 1.6; border-left: 4px solid #cbd5e1;">
                   <i style="color: #475569;">"{{ $returnOrder->message ?? 'Your request is currently being reviewed by our fulfillment team. We will notify you as soon as the next step is ready.' }}"</i>
                </div>
            </div>

            <div style="text-align: center; margin-bottom: 10px;">
                <a href="{{ url('/orders/' . $returnOrder->order_id) }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);">
                    Review Full Return Details
                </a>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 25px; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="margin: 0; color: #94a3b8; font-size: 12px; font-weight: 500;">
                VENDOR MANAGEMENT SYSTEM • {{ date('Y') }}
            </p>
            <p style="margin: 10px 0 0; color: #cbd5e1; font-size: 11px;">
                This is an automated system notification regarding your return request.
            </p>
        </div>
    </div>
</div>