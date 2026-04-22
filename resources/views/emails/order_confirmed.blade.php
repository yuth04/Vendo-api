<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Order Confirmed - #{{ $order->order_number }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
            }

            .content-padding {
                padding: 30px 20px !important;
            }

            .mobile-stack {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
            }
        }
    </style>
</head>

<body style="background-color: #f8fafc; padding: 40px 10px;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
        <tr>
            <td align="center">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%"
                    style="max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">

                    <tr>
                        <td style="padding: 40px; border-bottom: 1px solid #f1f5f9; background-color: #ffffff;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td class="mobile-stack">
                                        <h1 style="margin: 0; color: #2b3a55; font-size: 26px; font-weight: 800;">Order
                                            Confirmed</h1>
                                        <p style="margin: 4px 0 0; color: #94a3b8; font-size: 13px;">Hi
                                            {{ $order->user->first_name }}, your items are being prepared.</p>
                                    </td>
                                    <td align="right" class="mobile-stack" style="padding-top: 10px;">
                                        <div
                                            style="background-color: #f1f5f9; padding: 8px 15px; border-radius: 6px; display: inline-block;">
                                            <span
                                                style="display: block; font-size: 10px; color: #64748b; text-transform: uppercase; font-weight: 700; letter-spacing: 1px;">Order
                                                Number</span>
                                            <span
                                                style="font-size: 14px; font-weight: 700; color: #1e293b;">#{{ $order->order_number }}</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <div
                                style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px; text-align: center;">
                                <p style="margin: 0; font-size: 14px; color: #166534; font-weight: 600;">
                                    Current Status: <span style="text-transform: uppercase;">{{ $order->status }}</span>
                                </p>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                style="background-color: #f8fafc; border-radius: 8px; padding: 20px;">
                                <tr>
                                    <td width="50%" valign="top" class="mobile-stack" style="padding-bottom: 20px;">
                                        <div
                                            style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">
                                            Shipping Address</div>
                                        <div style="font-size: 14px; color: #1e293b; line-height: 1.5;">
                                            <strong>{{ $order->user->first_name }}
                                                {{ $order->user->last_name }}</strong><br>
                                            {{ $order->address->address_line ?? 'Address N/A' }}<br>
                                            {{ $order->address->city }}, {{ $order->address->province }}<br>
                                            {{ $order->address->phone }}
                                        </div>
                                    </td>
                                    <td width="50%" valign="top" align="right" class="mobile-stack">
                                        <div
                                            style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px;">
                                            Payment Details</div>
                                        <div style="font-size: 14px; color: #1e293b; line-height: 1.5;">
                                            <strong>{{ $order->payment->payment_method ?? 'Bakong KHQR' }}</strong><br>
                                            <span style="color: #64748b;">Method: Online</span><br>
                                            <span style="color: #10b981; font-weight: 600;">Payment Verified</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                <thead>
                                    <tr>
                                        <th align="left"
                                            style="padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-size: 12px; color: #94a3b8; text-transform: uppercase;">
                                            Item Description</th>
                                        <th align="center"
                                            style="padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-size: 12px; color: #94a3b8; text-transform: uppercase;">
                                            Qty</th>
                                        <th align="right"
                                            style="padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; font-size: 12px; color: #94a3b8; text-transform: uppercase;">
                                            Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->items as $item)
                                        <tr>
                                            <td style="padding: 20px 0; border-bottom: 1px solid #f1f5f9;">
                                                <div style="font-size: 15px; font-weight: 700; color: #1e293b;">
                                                    {{ $item->product_name }}</div>
                                                <div style="font-size: 12px; color: #94a3b8;">{{ $item->size }} /
                                                    {{ $item->color }}</div>
                                            </td>
                                            <td align="center"
                                                style="padding: 20px 0; border-bottom: 1px solid #f1f5f9; color: #64748b; font-size: 14px;">
                                                {{ $item->quantity }}</td>
                                            <td align="right"
                                                style="padding: 20px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; font-weight: 700; color: #1e293b;">
                                                ${{ number_format($item->price * $item->quantity, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 30px 40px;">
                            <table align="right" width="240" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding: 5px 0; font-size: 14px; color: #64748b;">Subtotal</td>
                                    <td align="right" style="padding: 5px 0; font-size: 14px; color: #1e293b;">
                                        ${{ number_format($order->total_price - $order->shipping_fee, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 5px 0; font-size: 14px; color: #64748b;">Shipping</td>
                                    <td align="right" style="padding: 5px 0; font-size: 14px; color: #1e293b;">
                                        ${{ number_format($order->shipping_fee, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 15px 0 0; font-size: 18px; font-weight: 800; color: #2b3a55;">
                                        Total</td>
                                    <td align="right"
                                        style="padding: 15px 0 0; font-size: 18px; font-weight: 800; color: #2b3a55;">
                                        ${{ number_format($order->total_price, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px; text-align: center;">
                            <hr style="border: 0; border-top: 1px solid #f1f5f9; margin-bottom: 30px;">
                      
                            <p style="margin: 25px 0 0; color: #94a3b8; font-size: 12px; line-height: 1.6;">
                                We'll send you another email with a tracking number once your order ships.<br>
                                Need help? Contact us at <strong>support@vendo.com</strong>
                            </p>
                        </td>
                    </tr>
                </table>

                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 600px;">
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