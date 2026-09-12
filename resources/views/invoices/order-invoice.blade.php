<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>
        Order Invoice - {{ $order->order_number }}
    </title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .invoice {
            width: 100%;
            padding: 30px;
        }

        /* ================= HEADER ================= */

        .header {
            width: 100%;
            margin-bottom: 25px;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

        .store-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .store-subtitle {
            font-size: 10px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
        }

        .store-info {
            color: #666;
            line-height: 1.7;
        }

        .invoice-title {
            text-align: right;
            font-size: 25px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .invoice-number {
            text-align: right;
            margin-top: 8px;
            color: #666;
            line-height: 1.8;
        }

        .invoice-number strong {
            color: #333;
        }

        .divider {
            border-top: 2px solid #333;
            margin: 20px 0;
        }

        /* ================= INFO ================= */

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }

        .info-table td {
            width: 50%;
            vertical-align: top;
            padding: 5px 0;
        }

        .info-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 8px;
        }

        .label {
            color: #777;
            display: inline-block;
            width: 115px;
        }

        .info-line {
            line-height: 1.8;
        }

        /* ================= BADGES ================= */

        .badge {
            display: inline-block;
            padding: 3px 9px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-paid {
            background: #e6f4ea;
            color: #1a6b30;
        }

        .badge-pending {
            background: #fff8e1;
            color: #a16500;
        }

        .badge-cancelled {
            background: #fce8e6;
            color: #a02020;
        }

        .badge-confirmed {
            background: #f0f0ff;
            color: #3c3489;
        }

        .badge-shipping {
            background: #e8f0fe;
            color: #1549a8;
        }

        .badge-delivered {
            background: #e6f4ea;
            color: #1a6b30;
        }

        .badge-completed {
            background: #e1f5ee;
            color: #085041;
        }

        .badge-returned {
            background: #fff0e6;
            color: #a04010;
        }

        .badge-default {
            background: #f0f0f5;
            color: #555;
        }

        /* ================= ITEMS ================= */

        .section-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .items-table th {
            background: #f2f2f2;
            border: 1px solid #ddd;
            padding: 9px;
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
        }

        .items-table td {
            border: 1px solid #ddd;
            padding: 9px;
            vertical-align: middle;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .product-name {
            font-weight: bold;
            color: #333;
        }

        .variant {
            color: #666;
            font-size: 11px;
        }

        /* ================= PAYMENT / NOTES ================= */

        .bottom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
        }

        .bottom-table>tbody>tr>td {
            vertical-align: top;
            width: 50%;
        }

        .notes-box {
            padding-right: 25px;
        }

        .notes {
            color: #777;
            line-height: 1.7;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 7px;
        }

        .totals-table .total {
            border-top: 2px solid #333;
            font-size: 15px;
            font-weight: bold;
        }

        .discount {
            color: #c0392b;
        }

        /* ================= PAYMENT BOX ================= */

        .payment-box {
            margin-top: 25px;
            padding: 15px;
            border: 1px solid #ddd;
        }

        .payment-title {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .payment-line {
            line-height: 1.8;
        }

        /* ================= FOOTER ================= */

        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #777;
            font-size: 10px;
            line-height: 1.7;
        }
    </style>
</head>

<body>

    <div class="invoice">

        {{-- ================= HEADER ================= --}}
        <div class="header">
            <table class="header-table">
                <tr>
                    {{-- STORE --}}
                    <td>
                        <div class="store-name">
                            VENDO STORE
                        </div>
                        <div class="store-subtitle">
                            Premium Marketplace
                        </div>
                        <div class="store-info">
                            Phnom Penh, Cambodia<br>
                            support@vendo.com<br>
                            +855 XX XXX XXX
                        </div>
                    </td>

                    {{-- INVOICE --}}
                    <td>
                        <div class="invoice-title">
                            INVOICE
                        </div>
                        <div class="invoice-number">
                            Invoice No:
                            <strong>{{ $order->order_number }}</strong>
                            <br>

                            Date:
                            <strong>{{ optional($order->created_at)->format('d M Y') }}</strong>
                            <br>

                            Status:
                            @php
                                $statusClass = match ($order->status) {
                                    'paid' => 'badge-paid',
                                    'pending' => 'badge-pending',
                                    'cancelled' => 'badge-cancelled',
                                    'confirmed' => 'badge-confirmed',
                                    'shipping' => 'badge-shipping',
                                    'delivered' => 'badge-delivered',
                                    'completed' => 'badge-completed',
                                    'returned' => 'badge-returned',
                                    default => 'badge-default',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">
                                {{ ucfirst($order->status ?? 'Pending') }}
                            </span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="divider"></div>

        {{-- ================= CUSTOMER / ORDER INFORMATION ================= --}}
        <table class="info-table">
            <tr>
                {{-- CUSTOMER --}}
                <td>
                    <div class="info-title">
                        Customer Information
                    </div>
                    <div class="info-line">
                        <strong>
                            {{ $order->user->first_name ?? '' }}
                            {{ $order->user->last_name ?? '' }}
                        </strong>
                        <br>
                        <span class="label">Email:</span>
                        {{ $order->user->email ?? 'N/A' }}
                        <br>
                        <span class="label">Contact:</span>
                        {{ $order->contact ?? 'N/A' }}
                    </div>
                </td>

                {{-- ORDER --}}
                <td>
                    <div class="info-title">
                        Order Information
                    </div>
                    <div class="info-line">
                        <span class="label">Order No:</span>
                        {{ $order->order_number }}
                        <br>
                        <span class="label">Payment:</span>
                        {{ ucfirst(optional($order->payment)->payment_method ?? 'N/A') }}
                        <br>
                        <span class="label">Payment Status:</span>
                        {{ ucfirst(optional($order->payment)->status ?? 'Pending') }}
                    </div>
                </td>
            </tr>
        </table>

        {{-- ================= SHIPPING ADDRESS ================= --}}
        @if($order->address)
            <div class="info-title">
                Shipping Address
            </div>
            <div class="info-line" style="margin-bottom: 25px;">
                {{ $order->address->address_line ?? '' }}<br>
                {{ $order->address->city ?? '' }},
                {{ $order->address->province ?? '' }}
                @if($order->address->postal_code)
                    {{ $order->address->postal_code }}
                @endif
                <br>
                Phone: {{ $order->address->phone ?? 'N/A' }}
            </div>
        @endif

        {{-- ================= ORDER ITEMS ================= --}}
        <div class="section-title">
            Order Items
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 6%;">#</th>
                    <th style="width: 34%;">Product</th>
                    <th style="width: 20%;">Variant</th>
                    <th style="width: 10%;" class="text-center">Qty</th>
                    <th style="width: 15%;" class="text-right">Unit Price</th>
                    <th style="width: 15%;" class="text-right">Amount</th>
                </tr>
            </thead>

            <tbody>
                @foreach($order->items as $index => $item)
                    <tr>
                        <td class="text-center">
                            {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                        </td>

                        {{-- PRODUCT --}}
                        <td>
                            <div class="product-name">
                                {{ $item->product_name ?? 'N/A' }}
                            </div>
                        </td>

                        {{-- VARIANT --}}
                        <td>
                            <div class="variant">
                                @if($item->size || $item->color)
                                    @if($item->size && $item->color) @endif
                                    @if($item->color)
                                        Color: {{ $item->color }}
                                    @endif
                                    /
                                    @if($item->size)
                                        Size: {{ $item->size }}
                                    @endif

                                @elseif($item->variant)
                                    {{ $item->variant->name ?? $item->variant->variant_name ?? $item->variant->sku ?? 'N/A' }}
                                @else
                                    N/A
                                @endif
                            </div>
                        </td>

                        {{-- QUANTITY --}}
                        <td class="text-center">
                            {{ $item->quantity }}
                        </td>

                        {{-- UNIT PRICE --}}
                        <td class="text-right">
                            ${{ number_format($item->variant->product->price, 2) }}
                        </td>

                        {{-- TOTAL --}}
                        <td class="text-right">
                            <strong>
                                ${{ number_format($item->variant->product->price * $item->quantity, 2) }}
                            </strong>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- ================= NOTES + TOTALS ================= --}}
        <table class="bottom-table">
            <tr>
                {{-- NOTES --}}
                <td class="notes-box">
                    <div class="info-title">
                        Notes
                    </div>
                    <div class="notes">
                        @if($order->note)
                            {{ $order->note }}
                        @else
                            Thank you for your purchase. For any questions regarding this invoice, please contact
                            support@vendo.com.
                        @endif
                    </div>
                </td>

                {{-- TOTALS --}}
                <td>
                    <table class="totals-table">
                        {{-- SUBTOTAL --}}
                        <tr>
                            <td>Subtotal</td>
                            <td class="text-right">
                                ${{ number_format($order->original_price ?? 0, 2) }}
                            </td>
                        </tr>

                        {{-- DISCOUNT --}}
                        @if(($order->discount_amount ?? 0) > 0)
                            <tr>
                                <td>Discount</td>
                                <td class="text-right discount">
                                    −${{ number_format($order->discount_amount, 2) }}
                                </td>
                            </tr>
                        @endif

                        {{-- COUPON --}}
                        @if(($order->coupon_discount ?? 0) > 0)
                            <tr>
                                <td>
                                    Coupon
                                    @if($order->coupon_code)
                                        <span style="font-size:10px;color:#999;">({{ $order->coupon_code }})</span>
                                    @endif
                                </td>
                                <td class="text-right discount">
                                    −${{ number_format($order->coupon_discount, 2) }}
                                </td>
                            </tr>
                        @endif

                        {{-- SHIPPING --}}
                        <tr>
                            <td>Shipping</td>
                            <td class="text-right">
                                ${{ number_format($order->shipping_fee ?? 0, 2) }}
                            </td>
                        </tr>

                        {{-- GRAND TOTAL --}}
                        <tr class="total">
                            <td>GRAND TOTAL</td>
                            <td class="text-right">
                                ${{ number_format($order->total_price, 2) }}
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {{-- ================= PAYMENT INFORMATION ================= --}}
        <div class="payment-box">
            <div class="payment-title">
                Payment Information
            </div>
            <div class="payment-line">
                <span class="label">Payment Method:</span>
                {{ ucfirst(optional($order->payment)->payment_method ?? 'N/A') }}
                <br>
                <span class="label">Payment Status:</span>
                {{ ucfirst(optional($order->payment)->status ?? 'Pending') }}

                @if($order->payment_transaction_id)
                    <br>
                    <span class="label">Transaction ID:</span>
                    {{ $order->payment_transaction_id }}
                @endif
            </div>
        </div>

        {{-- ================= FOOTER ================= --}}
        <div class="footer">
            <strong>Thank you for shopping with VENDO STORE.</strong>

        </div>

    </div>

</body>

</html>