<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Order Delivered - Vendo</title>
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
                    style="max-width: 580px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">

                    <tr>
                        <td style="padding: 48px 40px 32px; text-align: center;">
                            <div style="margin-bottom: 20px;">
                                <span
                                    style="display: inline-block; background-color: #ecfdf5; color: #059669; padding: 12px 24px; border-radius: 99px; font-weight: 700; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">
                                    Delivered Successfully
                                </span>
                            </div>
                            <h1
                                style="margin: 0; color: #1e293b; font-size: 28px; font-weight: 800; letter-spacing: -0.02em;">
                                It's officially yours!</h1>
                            <p style="margin: 12px 0 0; color: #64748b; font-size: 16px; line-height: 1.5;">
                                Hi {{ $order->user->first_name }}, your order
                                <strong>#{{ $order->order_number }}</strong> has been delivered and is waiting for you.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px 40px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #f1f5f9;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td class="mobile-stack" style="padding-bottom: 10px;">
                                                    <div
                                                        style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                                                        Delivered On</div>
                                                    <div style="font-size: 14px; color: #1e293b; font-weight: 600;">
                                                        {{ $order->delivered_at ? \Carbon\Carbon::parse($order->delivered_at)->format('d M Y, h:i A') : 'Recently' }}
                                                    </div>
                                                </td>
                                                <td align="right" class="mobile-stack">
                                                    <div
                                                        style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                                                        Payment Reference</div>
                                                    <div
                                                        style="font-size: 14px; color: #1e293b; font-family: ui-monospace, monospace;">
                                                        {{ $payment_reference ?? 'N/A' }}
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 16px 0;">
                                        <div style="font-size: 12px; color: #64748b; line-height: 1.6;">
                                            <strong>Payment Method:</strong>
                                            {{ ucfirst($order->payment->payment_method) }} &nbsp;•&nbsp;
                                            <strong>Status:</strong> <span
                                                style="color: #059669; font-weight: 600;">Paid</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 0 40px;">
                            <h4
                                style="margin: 0 0 16px; font-size: 14px; color: #1e293b; text-transform: uppercase; letter-spacing: 0.05em;">
                                Your Items</h4>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                @foreach($order->items as $item)
                                    <tr>
                                        <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                            <div style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                                {{ $item->product_name }}
                                            </div>
                                            <div style="font-size: 12px; color: #94a3b8;">{{ $item->size }} /
                                                {{ $item->color }} (x{{ $item->quantity }})
                                            </div>
                                        </td>
                                        <td align="right"
                                            style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; font-weight: 600; color: #1e293b;">
                                            ${{ number_format($item->price * $item->quantity, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 24px 40px 40px;">
                            <table align="right" width="200" border="0" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td style="padding-bottom: 8px; font-size: 14px; color: #64748b;">Shipping</td>
                                    <td align="right" style="padding-bottom: 8px; font-size: 14px; color: #1e293b;">
                                        ${{ number_format($order->shipping_fee, 2) }}</td>
                                </tr>
                                <tr>
                                    <td
                                        style="padding-top: 8px; border-top: 1px solid #f1f5f9; font-size: 16px; font-weight: 800; color: #2b3a55;">
                                        Total</td>
                                    <td align="right"
                                        style="padding-top: 8px; border-top: 1px solid #f1f5f9; font-size: 16px; font-weight: 800; color: #2b3a55;">
                                        ${{ number_format($order->total_price, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td
                            style="padding: 40px; background-color: #fcfcfc; text-align: center; border-top: 1px solid #f1f5f9;">
                            @if(!isset($isPdf) || !$isPdf)
                                <a href="{{ $downloadUrl ?? '#' }}" target="_blank"
                                    style="display: inline-block; background-color: #2b3a55; color: #ffffff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px;">
                                    View Invoice
                                </a>
                            @endif
                            <!-- <a href="#"
                                style="display: inline-block; background-color: #2b3a55; color: #ffffff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px;">
                                Rate Your Purchase
                            </a> -->
                            <p style="margin: 32px 0 0; color: #94a3b8; font-size: 12px; line-height: 1.6;">
                                Thank you for shopping with <strong>VENDO</strong> ❤️<br>
                                Not what you expected?You can request a return within 7 days. <a href="#"
                                    style="color: #64748b; text-decoration: underline;">Start a Return</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px;">

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