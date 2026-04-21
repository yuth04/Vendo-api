<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Return Completed - Vendo</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        @media screen and (max-width: 600px) {
            .container { width: 100% !important; }
            .content-padding { padding: 30px 20px !important; }
            .mobile-stack { display: block !important; width: 100% !important; text-align: left !important; }
        }
    </style>
</head>
<body style="background-color: #f8fafc; padding: 40px 10px;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
        <tr>
            <td align="center">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width: 580px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                    
                    <tr>
                        <td style="padding: 48px 40px 32px; text-align: center;">
                            <div style="margin-bottom: 20px;">
                                <span style="display: inline-block; background-color: #f1f5f9; color: #1e293b; padding: 12px 24px; border-radius: 99px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                    Return Processed
                                </span>
                            </div>
                            <h1 style="margin: 0; color: #1e293b; font-size: 28px; font-weight: 800; letter-spacing: -0.02em;">Refund is on its way!</h1>
                            <p style="margin: 12px 0 0; color: #64748b; font-size: 16px; line-height: 1.5;">
                                Hi {{ $returnOrder->user->name }}, your return for order <strong>#{{ $returnOrder->id }}</strong> has been successfully completed and restocked.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td class="mobile-stack" style="padding-bottom: 10px;">
                                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Completed On</div>
                                                    <div style="font-size: 14px; color: #1e293b; font-weight: 600;">
                                                        {{ $returnOrder->completed_at ? $returnOrder->completed_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A') }}
                                                    </div>
                                                </td>
                                                <td align="right" class="mobile-stack">
                                                    <div style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">Refund Status</div>
                                                    <div style="font-size: 14px; color: #2b3a55; font-weight: 700;">{{ strtoupper($returnOrder->refund_status) }}</div>
                                                </td>
                                            </tr>
                                        </table>
                                        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 16px 0;">
                                        <div style="font-size: 12px; color: #64748b; line-height: 1.6;">
                                            <strong>Return Status:</strong> <span style="color: #2b3a55; font-weight: 600;">{{ ucfirst($returnOrder->status) }}</span> &nbsp;•&nbsp; <strong>Action:</strong> Restocked
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px;">
                            <h4 style="margin: 0 0 16px; font-size: 14px; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em;">Returned Items</h4>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                @foreach($returnOrder->items as $item)
                                <tr>
                                    <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                        {{-- Note: Adjust based on your relationship names --}}
                                        <div style="font-size: 14px; font-weight: 600; color: #1e293b;">{{ $item->orderItem->product->name ?? 'Product' }}</div>
                                        <div style="font-size: 12px; color: #94a3b8;">Qty: {{ $item->quantity }}</div>
                                    </td>
                                    <td align="right" style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; font-weight: 600; color: #1e293b;">
                                        Processed
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 40px; background-color: #fcfcfc; text-align: center; border-top: 1px solid #f1f5f9;">
                            <p style="margin: 0 0 24px; color: #1e293b; font-weight: 600; font-size: 15px;">Need help with something else?</p>
                            <a href="{{ url('/') }}" style="display: inline-block; background-color: #2b3a55; color: #ffffff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px;">
                                Visit Our Support Center
                            </a>
                            <p style="margin: 32px 0 0; color: #94a3b8; font-size: 12px; line-height: 1.6;">
                                Thank you for shopping with <strong>VENDO</strong> ❤️<br>
                                We hope to see you again soon.
                            </p>
                        </td>
                    </tr>
                </table>

                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 580px;">
                    <tr>
                        <td style="padding: 30px; text-align: center; color: #94a3b8; font-size: 11px;">
                            &copy; {{ date('Y') }} VENDO. All rights reserved.<br>
                            Phnom Penh, Cambodia.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>