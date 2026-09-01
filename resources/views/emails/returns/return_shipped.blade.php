<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Exchange Item Shipped - Vendo</title>
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

                    {{-- Header Badge & Title --}}
                    <tr>
                        <td style="padding: 48px 40px 32px; text-align: center;">
                            <div style="margin-bottom: 20px;">
                                <span
                                    style="display: inline-block; background-color: #e0f2fe; color: #0369a1; padding: 10px 22px; border-radius: 99px; font-weight: 700; font-size: 13px; text-transform: uppercase; letter-spacing: 1px;">
                                    🚚 Replacement Shipped
                                </span>
                            </div>

                            <h1
                                style="margin: 0; color: #1e293b; font-size: 26px; font-weight: 800; letter-spacing: -0.02em;">
                                Your exchange item is on its way!</h1>
                            <p style="margin: 12px 0 0; color: #64748b; font-size: 15px; line-height: 1.6;">
                                Hi <strong>{{ $returnOrder->user->first_name ?? $returnOrder->user->name }}</strong>,
                                great news! We have dispatched your replacement item for Return Request
                                <strong>#{{ $returnOrder->id }}</strong> (Original Order
                                <strong>#{{ $returnOrder->order_id }}</strong>).
                            </p>
                        </td>
                    </tr>

                    {{-- Shipping Info Card --}}
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <table width="100%" border="0" cellspacing="0" cellpadding="0"
                                style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                            <tr>
                                                <td class="mobile-stack" style="padding-bottom: 12px;">
                                                    <div
                                                        style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                                                        Courier Service</div>
                                                    <div style="font-size: 14px; color: #1e293b; font-weight: 600;">
                                                        {{ $returnOrder->shipping_carrier ?? 'Local Express Delivery' }}
                                                    </div>
                                                </td>
                                                <td align="right" class="mobile-stack" style="padding-bottom: 12px;">
                                                    <div
                                                        style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                                                        Tracking Number</div>
                                                    <div style="font-size: 14px; color: #0284c7; font-weight: 700;">
                                                        {{ $returnOrder->tracking_number ?? 'Pending / In Transit' }}
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>

                                        @if($returnOrder->tracking_url)
                                            <div style="margin-top: 16px; text-align: center;">
                                                <a href="{{ $returnOrder->tracking_url }}" target="_blank"
                                                    style="display: inline-block; background-color: #0284c7; color: #ffffff; padding: 10px 24px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px;">
                                                    Track Your Package
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Shipped Items --}}
                    <tr>
                        <td style="padding: 0 40px 32px;">
                            <h4
                                style="margin: 0 0 16px; font-size: 13px; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700;">
                                Shipped Replacement Items
                            </h4>
                            <table width="100%" border="0" cellspacing="0" cellpadding="0">
                                @foreach($returnOrder->items as $item)
                                    <tr>
                                        <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                                            <div style="font-size: 14px; font-weight: 600; color: #1e293b;">
                                                {{ $item->orderItem->product->name ?? 'Replacement Product' }}
                                            </div>
                                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                                Replacement Variant ID:
                                                <strong>#{{ $item->replacement_variant_id }}</strong>
                                            </div>
                                        </td>
                                        <td align="right"
                                            style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; font-weight: 600; color: #0284c7; vertical-align: top;">
                                            Qty: {{ $item->exchange_quantity ?? $item->quantity }}
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    {{-- Footer Callout --}}
                    <tr>
                        <td
                            style="padding: 32px 40px; background-color: #fafafa; text-align: center; border-top: 1px solid #f1f5f9;">
                            <p style="margin: 0 0 16px; color: #1e293b; font-weight: 600; font-size: 14px;">Need help
                                with your delivery?</p>
                            <a href="{{ url('/support') }}"
                                style="display: inline-block; background-color: #1e293b; color: #ffffff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px;">
                                Contact Support
                            </a>
                            <p style="margin: 28px 0 0; color: #94a3b8; font-size: 12px; line-height: 1.6;">
                                Thank you for shopping with <strong>VENDO</strong> ❤️<br>
                                We hope you enjoy your new product!
                            </p>
                        </td>
                    </tr>
                </table>

                {{-- Copyright --}}
                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 580px;">
                    <tr>
                        <td style="padding: 24px 30px; text-align: center; color: #94a3b8; font-size: 11px;">
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