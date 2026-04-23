<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>New Return Request - Vendo</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
            }

            .mobile-stack {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
            }

            .content-padding {
                padding: 25px 20px !important;
            }
        }
    </style>
</head>

<body style="background-color: #f1f5f9; padding: 40px 10px;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
        <tr>
            <td align="center">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%"
                    style="max-width: 600px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1); border: 1px solid #e2e8f0;">

                    <tr>
                        <td style="background-color: #2b3a55; padding: 32px 40px; text-align: center;">
                            <span
                                style="background: #fbbf24; color: #451a03; padding: 6px 14px; border-radius: 99px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; display: inline-block; margin-bottom: 12px;">
                                Action Required
                            </span>
                            <h2
                                style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 800; letter-spacing: -0.02em;">
                                New Return Request</h2>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 32px 40px 0;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                style="background-color: #f8fafc; border-radius: 12px; padding: 20px;">
                                <tr>
                                    <td class="mobile-stack" style="padding-bottom: 10px;">
                                        <div
                                            style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                                            Customer</div>
                                        <div style="font-size: 15px; color: #1e293b; font-weight: 700;">
                                            {{ $returnOrder->user->first_name }} {{ $returnOrder->user->last_name }}
                                        </div>
                                    </td>
                                    <td align="right" class="mobile-stack">
                                        <div
                                            style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                                            Original Order</div>
                                        <div style="font-size: 15px; color: #1e293b; font-weight: 700;">
                                            #{{ $returnOrder->order_id }}</div>
                                    </td>
                                           <td align="right" class="mobile-stack">
                                        <div
                                            style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                                            Refund Type</div>
                                        <div style="font-size: 15px; color: #1e293b; font-weight: 700;">
                                            {{ $returnOrder->type }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 32px 40px 0;">
                            <div
                                style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">
                                Requested Items</div>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                style="border: 1px solid #f1f5f9; border-radius: 12px; overflow: hidden;">
                                <thead style="background-color: #f8fafc;">
                                    <tr>
                                        <th align="left"
                                            style="padding: 12px 16px; font-size: 12px; color: #64748b; border-bottom: 1px solid #f1f5f9; font-weight: 600;">
                                            Product</th>
                                        <th align="center"
                                            style="padding: 12px 16px; font-size: 12px; color: #64748b; border-bottom: 1px solid #f1f5f9; font-weight: 600;">
                                            Qty</th>
                                        <th align="right"
                                            style="padding: 12px 16px; font-size: 12px; color: #64748b; border-bottom: 1px solid #f1f5f9; font-weight: 600;">
                                            Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($returnOrder->items as $item)
                                        <tr>
                                            <td style="padding: 16px; border-bottom: 1px solid #f1f5f9;">
                                                <div style="color: #1e293b; font-weight: 700; font-size: 14px;">
                                                    {{ $item->orderItem->product_name ?? 'Product Missing' }}
                                                </div>
                                                <div style="color: #94a3b8; font-size: 12px; margin-top: 2px;">
                                                    @if(isset($item->orderItem->color) || isset($item->orderItem->size))
                                                        {{ $item->orderItem->color }} / {{ $item->orderItem->size }}
                                                    @else
                                                        Standard Variant
                                                    @endif
                                                </div>
                                            </td>
                                            <td align="center"
                                                style="padding: 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-size: 14px; font-weight: 600;">
                                                {{ $item->quantity }}
                                            </td>
                                            <td align="right"
                                                style="padding: 16px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-weight: 700; font-size: 14px;">
                                                ${{ number_format($item->orderItem->price ?? 0, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 32px 40px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding-bottom: 16px;">
                                        <div
                                            style="display: block; background-color: #fff1f2; border-radius: 8px; padding: 12px 16px; border: 1px solid #ffe4e6;">
                                            <span
                                                style="color: #e11d48; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 4px;">Reason
                                                for Return</span>
                                            <span
                                                style="color: #9f1239; font-size: 15px; font-weight: 700;">{{ $returnOrder->reason }}</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div
                                            style="border-left: 4px solid #cbd5e1; background-color: #f8fafc; padding: 16px; border-radius: 0 8px 8px 0;">
                                            <div
                                                style="color: #94a3b8; font-size: 11px; font-weight: 800; text-transform: uppercase; margin-bottom: 8px;">
                                                Customer Feedback</div>
                                            <div
                                                style="color: #475569; font-size: 14px; line-height: 1.6; font-style: italic;">
                                                "{{ $returnOrder->note ?? 'No additional comments provided.' }}"
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <div style="text-align: center; margin-top: 40px;">
                                <a href="{{ url('/admin/returns/' . $returnOrder->id) }}"
                                    style="background-color: #2b3a55; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 14px; display: inline-block; box-shadow: 0 4px 6px rgba(43, 58, 85, 0.2);">
                                    Process Return in Dashboard
                                </a>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="background: #f8fafc; padding: 24px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p
                                style="margin: 0; color: #94a3b8; font-size: 11px; font-weight: 700; letter-spacing: 1px;">
                                VENDO OPERATIONS SYSTEM • {{ date('Y') }}
                            </p>
                        </td>
                    </tr>
                </table>

                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px;">
                    <tr>
                        <td style="padding: 30px; text-align: center; color: #94a3b8; font-size: 11px;">
                            This is an automated system notification.<br>
                            Please do not reply directly to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>