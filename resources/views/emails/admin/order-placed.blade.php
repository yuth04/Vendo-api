<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>New Order Alert - Vendo</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
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
                padding: 30px 20px !important;
            }
        }
    </style>
</head>

<body style="background-color: #f3f4f6; padding: 40px 10px;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
        <tr>
            <td align="center">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%"
                    style="max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);">

                    <tr>
                        <td style="padding: 40px; border-bottom: 1px solid #f1f5f9;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="mobile-stack">
                                        <h1
                                            style="margin: 0; color: #2b3a55; font-size: 24px; font-weight: 800; letter-spacing: -0.02em;">
                                            🛍️ New Order</h1>
                                        <p
                                            style="margin: 4px 0 0; color: #3b82f6; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                            Internal Notification
                                        </p>
                                    </td>
                                    <td align="right" class="mobile-stack" style="padding-top: 10px;">
                                        <div style="font-size: 16px; font-weight: 700; color: #1e293b;">
                                            #{{ $order->order_number }}</div>
                                        <div style="font-size: 12px; color: #94a3b8;">
                                            {{ $order->created_at->format('M d, Y • h:i A') }}</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 32px 40px; background-color: #fafafa;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="mobile-stack" width="50%" valign="top" style="padding-bottom: 20px;">
                                        <div
                                            style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                                            Ship To</div>
                                        <div style="font-size: 14px; color: #1e293b; line-height: 1.5;">
                                            <strong>{{ $order->user->first_name }}
                                                {{ $order->user->last_name }}</strong><br>
                                            {{ $order->address->address_line ?? '' }}<br>
                                            {{ $order->address->city }}, {{ $order->address->province }}<br>
                                            <span
                                                style="color: #3b82f6; font-weight: 600;">{{ $order->address->phone ?? '' }}</span>
                                        </div>
                                    </td>
                                    <td class="mobile-stack" width="50%" valign="top" style="padding-left: 10px;">
                                        <div
                                            style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                                            Payment Details</div>
                                        <div style="font-size: 14px; color: #1e293b; line-height: 1.5;">
                                            <strong>Method:</strong>
                                            {{ ucfirst($order->payment->payment_method ?? 'Online') }}<br>
                                            <strong>Status:</strong> <span
                                                style="color: #10b981; font-weight: 700;">Paid</span><br>
                                            <strong>Total: ${{ number_format($order->total_price, 2) }}</strong>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="content-padding" style="padding: 40px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <thead>
                                    <tr>
                                        <th align="left"
                                            style="padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-size: 12px; color: #94a3b8; text-transform: uppercase;">
                                            Product</th>
                                        <th align="center"
                                            style="padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-size: 12px; color: #94a3b8; text-transform: uppercase;">
                                            Qty</th>
                                        <th align="right"
                                            style="padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-size: 12px; color: #94a3b8; text-transform: uppercase;">
                                            Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->items as $item)
                                        <tr>
                                            <td style="padding: 16px 0; border-bottom: 1px solid #f8fafc;">
                                                <div style="font-size: 14px; font-weight: 700; color: #1e293b;">
                                                    {{ $item->product_name }}</div>
                                                <div style="font-size: 12px; color: #64748b;">{{ $item->size }} /
                                                    {{ $item->color }}</div>
                                            </td>
                                            <td align="center"
                                                style="padding: 16px 0; border-bottom: 1px solid #f8fafc; font-size: 14px; color: #1e293b; font-weight: 700;">
                                                {{ $item->quantity }}
                                            </td>
                                            <td align="right"
                                                style="padding: 16px 0; border-bottom: 1px solid #f8fafc; font-size: 14px; color: #1e293b; font-weight: 700;">
                                                ${{ number_format($item->price * $item->quantity, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <table align="right" width="200" border="0" cellspacing="0" cellpadding="0"
                                style="margin-top: 24px;">
                                <tr>
                                    <td style="padding-bottom: 8px; font-size: 13px; color: #64748b;">Shipping Fee</td>
                                    <td align="right"
                                        style="padding-bottom: 8px; font-size: 13px; font-weight: 700; color: #1e293b;">
                                        ${{ number_format($order->shipping_fee, 2) }}</td>
                                </tr>
                                <tr>
                                    <td
                                        style="padding-top: 8px; border-top: 2px solid #f1f5f9; font-size: 15px; font-weight: 800; color: #2b3a55;">
                                        Order Total</td>
                                    <td align="right"
                                        style="padding-top: 8px; border-top: 2px solid #f1f5f9; font-size: 15px; font-weight: 800; color: #2b3a55;">
                                        ${{ number_format($order->total_price, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px; text-align: center;">
                            <a href="{{ url('https://vendo-admin-panel.vercel.app/admin/login?order_id=' . $order->id) }}"
                                style="display: inline-block; background-color: #2b3a55; color: #ffffff; padding: 14px 40px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px;">
                                View Full Order Details
                            </a>
                        </td>
                    </tr>

                    <tr>
                        <td style="background-color: #1e293b; padding: 20px; text-align: center;">
                            <p
                                style="margin: 0; color: #94a3b8; font-size: 10px; text-transform: uppercase; letter-spacing: 2px; font-weight: 700;">
                                VENDO MANAGEMENT SYSTEM • {{ date('Y') }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>