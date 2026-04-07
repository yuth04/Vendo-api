<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>Invoice #{{ $order->order_number }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            line-height: 1.5;
        }

        .invoice-wrapper {
            max-width: 700px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        /* Header Section */
        .header-section {
            padding: 40px;
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
        }

        .brand-logo {
            font-size: 24px;
            font-weight: 800;
            color: #2b3a55;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .status-badge {
            display: inline-block;
            background-color: #ecfdf5;
            color: #059669;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 12px;
            border-radius: 99px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Info Grid */
        .grid-table {
            width: 100%;
            padding: 30px 40px;
        }

        .label-sm {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .value-md {
            font-size: 14px;
            color: #1e293b;
            font-weight: 500;
        }

        /* Line Items */
        .items-container {
            padding: 0 40px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .items-table th {
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #94a3b8;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
            text-transform: uppercase;
        }

        .items-table td {
            padding: 20px 0;
            border-bottom: 1px solid #f8fafc;
            vertical-align: middle;
        }

        .product-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
            margin-bottom: 4px;
        }

        .product-meta {
            font-size: 12px;
            color: #64748b;
        }

        /* Totals Area */
        .footer-section {
            padding: 40px;
            background-color: #fcfcfc;
        }

        .totals-table {
            width: 280px;
            margin-left: auto;
        }

        .totals-table td {
            padding: 8px 0;
            font-size: 14px;
        }

        .grand-total {
            border-top: 2px solid #2b3a55;
            margin-top: 10px;
            padding-top: 15px !important;
            font-size: 22px !important;
            font-weight: 800;
            color: #2b3a55;
        }

        @media only screen and (max-width: 600px) {
            .invoice-wrapper { margin: 0; border-radius: 0; }
            .grid-table, .items-container, .footer-section, .header-section { padding: 25px 20px; }
            .hide-mobile { display: none; }
        }
    </style>
</head>

<body>

    <div class="invoice-wrapper">
        <div class="header-section">
            <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <div class="brand-logo">VENDO INVOICE</div>
                        <div style="font-size: 14px; color: #64748b; margin-top: 4px;">Receipt #{{ $order->order_number }}</div>
                    </td>
                    <td align="right" valign="top">
                        <div class="status-badge">Paid</div>
                    </td>
                </tr>
            </table>
        </div>

        <table class="grid-table" border="0" cellpadding="0" cellspacing="0">
            <tr>
                <td width="50%" valign="top">
                    <div class="label-sm">Billed To</div>
                    <div class="value-md">
                        <strong>{{ $order->user->first_name }} {{ $order->user->last_name }}</strong><br>
                        <span style="color: #64748b; font-weight: 400;">
                            {{ $order->address->address_line ?? 'N/A' }}<br>
                            {{ $order->address->city }}, {{ $order->address->province }}
                        </span>
                    </div>
                </td>
                <td width="50%" valign="top" align="right">
                    <div class="label-sm">Order Details</div>
                    <div class="value-md">
                        Date: {{ $order->created_at->format('M d, Y') }}<br>
                        Method: {{ $order->payment->payment_method ?? 'Bakong KHQR' }}<br>
                        <span style="font-size: 11px; color: #94a3b8;">Ref: {{ $order->payment->payment_reference ?? 'N/A' }}</span>
                    </div>
                </td>
            </tr>
        </table>

        <div class="items-container">
            <table class="items-table" border="0" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <th width="70%">Description</th>
                        <th width="10%" style="text-align: center;">Qty</th>
                        <th width="20%" style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td>
                            <div class="product-name">{{ $item->product_name }}</div>
                            <div class="product-meta">Size: {{ $item->size }} &nbsp;•&nbsp; Color: {{ $item->color }}</div>
                        </td>
                        <td align="center" style="font-weight: 500;">{{ $item->quantity }}</td>
                        <td align="right" style="font-weight: 700; color: #1e293b;">
                            ${{ number_format($item->price * $item->quantity, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="footer-section">
            <table class="totals-table" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="color: #64748b;">Subtotal</td>
                    <td align="right" style="font-weight: 500;">${{ number_format($order->total_price - ($order->shipping_fee ?? 0), 2) }}</td>
                </tr>
                <tr>
                    <td style="color: #64748b;">Shipping Fee</td>
                    <td align="right" style="font-weight: 500;">${{ number_format($order->shipping_fee ?? 0, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2" class="grand-total">
                        <table width="100%">
                            <tr>
                                <td style="font-size: 16px; color: #2b3a55;">Amount Paid</td>
                                <td align="right" style="color: #2b3a55;">USD ${{ number_format($order->total_price, 2) }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <div style="margin-top: 60px; text-align: center;">
                <p style="font-size: 13px; color: #94a3b8; margin: 0;">
                    Thank you for your purchase from <strong>Vendo Store</strong>.
                </p>
                <div style="margin-top: 15px;">
                    <a href="#" style="font-size: 12px; color: #2b3a55; text-decoration: none; font-weight: 600;">Download PDF</a>
                    <span style="color: #cbd5e1; margin: 0 10px;">|</span>
                    <a href="#" style="font-size: 12px; color: #2b3a55; text-decoration: none; font-weight: 600;">Support Center</a>
                </div>
            </div>
        </div>
    </div>

    <div style="text-align: center; padding-bottom: 40px;">
        <p style="font-size: 11px; color: #94a3b8;">
            Vendo Store, Phnom Penh, Cambodia.<br>
            &copy; {{ date('Y') }} Vendo. All rights reserved.
        </p>
    </div>

</body>
</html>