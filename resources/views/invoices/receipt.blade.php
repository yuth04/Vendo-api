<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #{{ $order->order_number }}</title>
    <style>
        /* Base Styles */
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #1e293b;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
            line-height: 1.6;
        }

        .invoice-wrapper {
            max-width: 800px;
            margin: 20px auto;
            background: #ffffff;
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        /* Header */
        .header-centered {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 30px;
        }

        .brand-name {
            font-size: 36px;
            font-weight: 800;
            color: #2b3a55;
            letter-spacing: -1px;
        }

        .invoice-label {
            font-size: 16px;
            font-weight: 300;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 5px;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-td {
            vertical-align: top;
            padding: 10px 0;
        }

        .label {
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .value {
            font-size: 13px;
            font-weight: 600;
        }

        /* Item Table */
        .items-table {
            margin-top: 30px;
        }

        .items-table th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            color: #64748b;
            padding: 12px;
            border-bottom: 2px solid #f1f5f9;
        }

        .items-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f8fafc;
            font-size: 14px;
        }

        /* Totals */
        .totals-container {
            width: 100%;
            margin-top: 30px;
            text-align: right;
        }

        .totals-table {
            width: 250px;
            margin-left: auto;
        }

        .total-row {
            font-size: 20px;
            font-weight: 800;
            color: #2b3a55;
            border-top: 2px solid #2b3a55;
        }

        /* Responsive Logic */
        @media only screen and (max-width: 600px) {
            .invoice-wrapper {
                padding: 20px;
                margin: 0;
            }

            .brand-name {
                font-size: 28px;
            }

            /* Stack Info Grid */
            .info-table td {
                display: block;
                width: 100% !important;
                text-align: left !important;
                margin-bottom: 20px;
            }

            /* Hide non-essential columns on mobile to prevent squishing */
            .hide-mobile {
                display: none;
            }

            /* Make items look like cards */
            .items-table thead {
                display: none;
            }

            .items-table tr {
                display: block;
                margin-bottom: 15px;
                border: 1px solid #f1f5f9;
                padding: 10px;
                border-radius: 8px;
            }

            .items-table td {
                display: block;
                text-align: right;
                padding: 5px;
                border: none;
            }

            .items-table td:before {
                content: attr(data-label);
                float: left;
                font-weight: bold;
                color: #94a3b8;
                font-size: 11px;
                text-transform: uppercase;
            }

            .totals-table {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <div class="invoice-wrapper">
        <div class="header-centered">
            <div class="brand-name">VENDO</div>
            <div class="invoice-label">Official Receipt</div>
            <div style="font-size: 12px; color: #64748b; margin-top: 10px;">
                Order number: #{{ $order->order_number }} • {{ $order->created_at->format('M d, Y') }}
            </div>
        </div>

        <table class="info-table">
            <tr>
                <td width="50%" class="info-td">
                    <div class="label">Billed To</div>
                    <div class="value">
                        {{ $order->user->first_name }} {{ $order->user->last_name }}<br>
                        <span style="font-weight: 400; color: #64748b;">
                            {{ $order->address->address_line ?? 'N/A' }}<br>
                            {{ $order->address->city }}, {{ $order->address->province }}
                        </span>
                    </div>
                </td>
                <td width="50%" class="info-td" style="text-align: right;">
                    <div class="label">Payment Method</div>
                    <div class="value">
                        {{ $order->payment->payment_method ?? 'Bakong KHQR' }}<br>
                        <span style="font-weight: 400; color: #94a3b8; font-size: 11px;">
                            Ref: {{ $order->payment->payment_reference ?? 'N/A' }}
                        </span>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="60%">Item</th>
                    <th width="10%" style="text-align: center;">Qty</th>
                    <th width="30%" style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td data-label="Product">
                            <div style="font-weight: 700; color: #2b3a55;">{{ $item->product_name }}</div>
                            <div style="font-size: 11px; color: #94a3b8;">{{ $item->size }} / {{ $item->color }}</div>
                        </td>
                        <td data-label="Quantity" style="text-align: center;">{{ $item->quantity }}</td>
                        <td data-label="Price" style="text-align: right; font-weight: 700;">
                            ${{ number_format($item->price * $item->quantity, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-container">
            <table class="totals-table">
                <tr>
                    <td style="padding: 5px; color: #64748b;">Subtotal</td>
                    <td style="padding: 5px;">${{ number_format($order->total_price - 1.00, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding: 5px; color: #64748b;">Shipping</td>
                    <td style="padding: 5px;">USD ${{ number_format($order->shipping_fee ?? 0, 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td style="padding: 15px 5px;">Total</td>
                    <td style="padding: 15px 5px;">${{ number_format($order->total_price, 2) }}</td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 50px; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 20px;">
            <p style="font-size: 12px; color: #94a3b8;">
                Thank you for shopping at <strong>Vendo</strong>.<br>
                For support, contact support@vendo.com
            </p>
        </div>
    </div>

</body>

</html>