<div style="background-color: #f8fafc; padding: 50px 20px; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
        
        <div style="background: linear-gradient(135deg, #2b3a55 0%, #4a5d7e 100%); padding: 30px 25px; display: flex; align-items: center;">
            <div style="background: rgba(255,255,255,0.2); width: 45px; height: 45px; border-radius: 10px; margin-right: 15px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                📦
            </div>
            <h2 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 600;">New Return Request</h2>
        </div>

        <div style="padding: 35px; color: #333333; line-height: 1.6;">
            
            <p style="color: #64748b; font-size: 14px; margin-bottom: 25px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                A customer has submitted a new return request for <strong>Vendo</strong>. Please review the details below.
            </p>

            <div style="background-color: #f8fafc; border-radius: 12px; padding: 20px; border: 1px solid #f1f5f9; margin-bottom: 30px;">
                <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding-bottom: 10px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Order ID</td>
                        <td style="padding-bottom: 10px; color: #1e293b; font-weight: 600; text-align: right;">#{{ $returnOrder->order_id }}</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 10px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Customer</td>
                        <td style="padding-bottom: 10px; color: #1e293b; font-weight: 600; text-align: right;">
                            {{ $returnOrder->user->first_name }} {{ $returnOrder->user->last_name }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 10px; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">Reason</td>
                        <td style="padding-bottom: 10px; color: #ef4444; font-weight: 600; text-align: right;">{{ $returnOrder->reason }}</td>
                    </tr>
                </table>

                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                    <p style="margin: 0; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">Customer Note</p>
                    <p style="margin: 0; color: #475569; font-size: 14px; font-style: italic;">
                        "{{ $returnOrder->note ?? 'No additional notes provided.' }}"
                    </p>
                </div>
            </div>

            <div style="text-align: center;">
                <a href="{{ url('/admin/returns/' . $returnOrder->id) }}"
                   style="display: inline-block; padding: 14px 30px; background-color: #2b3a55; color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 12px rgba(43, 58, 85, 0.2);">
                    Review & Manage Request
                </a>
            </div>

            <p style="text-align: center; font-size: 12px; color: #94a3b8; margin-top: 25px;">
                Note: Approving this will automatically notify the customer via email.
            </p>
        </div>

        <div style="padding-bottom: 30px; text-align: center;">
            <div style="color: #cbd5e1; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;">
                Vendo Admin System
            </div>
        </div>
    </div>
</div>