<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Your Vendo Order is on its way!</title>
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
        }
    </style>
</head>

<body style="background-color: #f8fafc; padding: 40px 10px;">
    <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation">
        <tr>
            <td align="center">
                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%"
                    style="max-width: 580px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">

                    <tr>
                        <td style="height: 6px; background: linear-gradient(90deg, #4f46e5, #3b82f6);"></td>
                    </tr>

                    <tr>
                        <td style="padding: 48px 40px 32px; text-align: center;">
                            <div style="font-size: 40px; margin-bottom: 20px;">🚚</div>
                            <h1
                                style="margin: 0; color: #1e293b; font-size: 28px; font-weight: 800; letter-spacing: -0.02em;">
                                Your order is on the way!</h1>
                            <p style="margin: 12px 0 0; color: #64748b; font-size: 16px; line-height: 1.5;">
                                Great news {{ $order->user->first_name }}! Order
                                <strong>#{{ $order->order_number }}</strong> has been shipped and is currently in
                                transit.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                style="background-color: #f0f7ff; border-radius: 12px; border: 1px solid #e0eeff;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td valign="top">
                                                    <div
                                                        style="font-size: 11px; font-weight: 700; color: #5a67d8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                                                        Current Status</div>
                                                    <div style="font-size: 15px; color: #1e293b; font-weight: 700;">In
                                                        Transit</div>
                                                </td>
                                                <td align="right" valign="top">
                                                    <div
                                                        style="font-size: 11px; font-weight: 700; color: #5a67d8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                                                        Shipped Via</div>
                                                    <div style="font-size: 15px; color: #1e293b; font-weight: 700;">
                                                        Standard Delivery</div>
                                                </td>
                                            </tr>
                                        </table>
                                        <div
                                            style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #d0e4ff;">
                                            <span style="font-size: 13px; color: #4338ca;">
                                                <strong>Dispatch Date:</strong>
                                                {{ $order->shipped_at ? \Carbon\Carbon::parse($order->shipped_at)->format('d M Y, h:i A') : 'Today' }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px; text-align: center;">
                            <a href="https://vendo-ecommerce-two.vercel.app/account/orders"
                                style="display: inline-block; background-color: #2b3a55; color: #ffffff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 14px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                Track My Package
                            </a>
                            <p style="margin: 24px 0 0; color: #64748b; font-size: 14px; line-height: 1.6;">
                                You can view real-time updates and your order history at any time through your Vendo
                                dashboard.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px 40px; background-color: #fcfcfc; border-top: 1px solid #f1f5f9;">
                            <div
                                style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 12px;">
                                Shipping To</div>
                            <div style="font-size: 14px; color: #475569; line-height: 1.5;">
                                <strong>{{ $order->user->first_name }} {{ $order->user->last_name }}</strong><br>
                                {{ $order->address->address_line ?? '' }}<br>
                                {{ $order->address->city }}, {{ $order->address->province }}
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px 40px; text-align: center;">
                            <p style="margin: 0; color: #94a3b8; font-size: 12px; line-height: 1.6;">
                                Thank you for choosing <strong>VENDO</strong> ❤️<br>
                                Need help with your delivery? <a href="mailto:support@vendo.com"
                                    style="color: #64748b; text-decoration: underline;">Contact Support</a>
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