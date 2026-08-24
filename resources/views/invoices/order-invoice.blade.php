<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Invoice #{{ $order->order_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1a1a2e;
            background: #f4f4f8;
            padding: 32px;
        }

        .inv-card {
            background: #ffffff;
            border: 1px solid #e4e4ec;
            border-radius: 12px;
            overflow: hidden;
            max-width: 760px;
            margin: 0 auto;
        }

        .inv-stripe-top {
            height: 4px;
            background: #1a1a2e;
        }

        .inv-stripe-bot {
            height: 3px;
            background: #1a1a2e;
        }

        .inv-body {
            padding: 36px 40px;
        }

        /* ── HEADER ── */
        .inv-head {
            display: table;
            width: 100%;
            margin-bottom: 28px;
        }

        .col-brand {
            display: table-cell;
            width: 55%;
            vertical-align: top;
        }

        .col-meta {
            display: table-cell;
            width: 45%;
            vertical-align: top;
            text-align: right;
        }

        .brand-name {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #1a1a2e;
        }

        .brand-sub {
            font-size: 9px;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #888;
            margin-top: 3px;
            margin-bottom: 14px;
        }

        .brand-info {
            font-size: 11px;
            color: #666;
            line-height: 1.9;
        }

        .inv-label {
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 6px;
            text-transform: uppercase;
            color: #1a1a2e;
        }
        .inv-meta-rows {
            margin-top: 10px;
            font-size: 11px;
            color: #666;
            line-height: 2.1;
        }

        .inv-meta-rows strong {
            color: #1a1a2e;
        }

        /* Status badge */
        .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1px;
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

        .badge-shipping {
            background: #e8f0fe;
            color: #1549a8;
        }

        .badge-delivered {
            background: #e6f4ea;
            color: #1a6b30;
        }

        .badge-confirmed {
            background: #f0f0ff;
            color: #3c3489;
        }

        .badge-returned {
            background: #fff0e6;
            color: #a04010;
        }

        .badge-completed {
            background: #e1f5ee;
            color: #085041;
        }

        .badge-default {
            background: #f0f0f5;
            color: #555;
        }

        /* ── DIVIDER ── */
        .inv-divider {
            border: none;
            border-top: 1px solid #eeeef4;
            margin: 0 0 26px;
        }

        /* ── BILLING ── */
        .section-label {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #999;
            margin-bottom: 10px;
        }

        .billing-name {
            font-size: 14px;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .billing-detail {
            font-size: 11px;
            color: #666;
            line-height: 2;
        }

        /* ── ITEMS TABLE ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
        }

        .items-table thead tr {
            background: #1a1a2e;
        }

        .items-table thead th {
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 11px 13px;
            text-align: left;
        }

        .items-table thead th.c {
            text-align: center;
        }

        .items-table thead th.r {
            text-align: right;
        }

        .items-table tbody tr {
            border-bottom: 1px solid #eeeef4;
        }

        .items-table tbody tr.even {
            background: #f9f9fc;
        }

        .items-table tbody td {
            padding: 11px 13px;
            color: #555;
            vertical-align: middle;
        }

        .items-table tbody td.c {
            text-align: center;
        }

        .items-table tbody td.r {
            text-align: right;
        }

        .product-name {
            font-weight: bold;
            color: #1a1a2e;
            font-size: 12.5px;
        }

        .variant-tag {
            display: inline-block;
            background: #f0f0f5;
            border: 1px solid #e4e4ec;
            border-radius: 3px;
            font-size: 10px;
            padding: 1px 7px;
            color: #666;
        }

        .row-num {
            font-size: 10px;
            color: #bbb;
        }

        /* ── BOTTOM ── */
        .inv-bottom {
            display: table;
            width: 100%;
        }

        .col-notes {
            display: table-cell;
            width: 52%;
            vertical-align: top;
            padding-right: 24px;
        }

        .col-totals {
            display: table-cell;
            width: 48%;
            vertical-align: top;
        }

        .notes-text {
            font-size: 11px;
            color: #aaa;
            line-height: 1.9;
            margin-top: 8px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }

        .totals-table td {
            padding: 9px 13px;
            border-bottom: 1px solid #eeeef4;
            color: #666;
        }

        .totals-table td:last-child {
            text-align: right;
        }

        .totals-table .grand td {
            background: #1a1a2e;
            color: #ffffff;
            font-weight: bold;
            font-size: 13px;
            border-bottom: none;
        }

        .totals-table .pmnt td {
            background: #f9f9fc;
            border-bottom: none;
            font-size: 11px;
        }

        .discount-val {
            color: #c0392b;
        }

        /* ── FOOTER ── */
        .inv-footer {
            display: table;
            width: 100%;
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid #eeeef4;
        }

        .footer-left {
            display: table-cell;
            width: 60%;
            vertical-align: middle;
        }

        .footer-right {
            display: table-cell;
            width: 40%;
            vertical-align: middle;
            text-align: right;
        }

        .footer-ty {
            font-size: 13px;
            font-weight: bold;
            color: #1a1a2e;
        }

        .footer-gen {
            font-size: 10px;
            color: #bbb;
            margin-top: 3px;
        }

        .footer-brand {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 4px;
            text-transform: uppercase;
            color: #ddd;
        }
    </style>
</head>

<body>

    <div class="inv-card">
        <div class="inv-stripe-top"></div>
        <div class="inv-body">

            {{-- ══ HEADER ══ --}}
            <div class="inv-head">
                <div class="col-brand">
                    <div class="brand-name">Vendo</div>
                    <div class="brand-sub">Premium Marketplace</div>
                    <div class="brand-info">
                        Phnom Penh, Cambodia<br>
                        support@vendo.com &nbsp;·&nbsp; +855 XX XXX XXX
                    </div>
                </div>
                <div class="col-meta">
                    <div class="inv-label">Invoice</div>
                    <div class="inv-meta-rows">
                        Invoice # &nbsp;<strong>{{ $order->order_number }}</strong><br>
                        Date &nbsp;<strong>{{ $order->created_at->format('d M Y') }}</strong><br>
                        Status &nbsp;
                        @php
                            $cls = match ($order->status) {
                                'paid' => 'badge-paid',
                                'pending' => 'badge-pending',
                                'cancelled' => 'badge-cancelled',
                                'shipping' => 'badge-shipping',
                                'delivered' => 'badge-delivered',
                                'confirmed' => 'badge-confirmed',
                                'returned' => 'badge-returned',
                                'completed' => 'badge-completed',
                                default => 'badge-default',
                            };
                        @endphp
                        <span class="badge {{ $cls }}">{{ ucfirst($order->status) }}</span> <br/>
                        
                    </div>

                    <span class="badge ">{{ ucfirst($order->payment->payment_method) }}</span>
                </div>
            </div>

            <hr class="inv-divider">

            {{-- ══ BILLING ══ --}}
            <div style="margin-bottom:28px;">
                <div class="section-label">Bill to</div>
                <div class="billing-name">
                    {{ $order->user->first_name }} {{ $order->user->last_name }}
                </div>
                <div class="billing-detail">
                    {{ $order->user->email }}<br>
                    @if($order->address)
                        {{ $order->address->address_line }}<br>
                        {{ $order->address->city }}, {{ $order->address->province }} {{ $order->address->postal_code }}<br>
                        {{ $order->address->phone }}
                    @else
                        <span style="color:#ccc;">No address provided</span>
                    @endif
                </div>
            </div>

            {{-- ══ ITEMS ══ --}}
            <div class="section-label" style="margin-bottom:12px;">Order items</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:40%">Product</th>
                        <th class="c" style="width:14%">Variant</th>
                        <th class="c" style="width:9%">Qty</th>
                        <th class="r" style="width:16%">Unit price</th>
                        <th class="r" style="width:16%">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $index => $item)
                        <tr class="{{ $index % 2 === 1 ? 'even' : '' }}">
                            <td><span class="row-num">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</span></td>
                            <td>
                                <div class="product-name">{{ $item->product_name ?? 'N/A' }}</div>
                            </td>
                            <td class="c">
                                @if(optional($item->variant)->size)
                                    <span class="variant-tag">{{ $item->variant->size }}</span>
                                @else
                                    <span style="color:#ddd;">—</span>
                                @endif
                            </td>
                            <td class="c">{{ $item->quantity }}</td>
                            <td class="r">${{ number_format($item->order->original_price, 2) }}</td>
                            <td class="r" style="font-weight:bold;color:#1a1a2e;">
                                ${{ number_format($item->price * $item->quantity, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- ══ NOTES + TOTALS ══ --}}
            <div class="inv-bottom">
                <div class="col-notes">
                    <div class="section-label">Notes</div>
                    <div class="notes-text">
                        @if($order->note)
                            {{ $order->note }}
                        @else
                            Thank you for your purchase. For any questions regarding
                            this invoice, please contact support@vendo.com with your order number.
                        @endif
                    </div>
                </div>

                <div class="col-totals">
                    <table class="totals-table">

                        {{-- Subtotal (before any deductions) --}}
                        <tr>
                            <td>Subtotal</td>
                            <td>${{ number_format($order->sub_total_price, 2) }}</td>
                        </tr>

                        {{-- Discount amount (e.g. sale / manual discount) --}}
                        @if(($order->discount_amount ?? 0) > 0)
                            <tr>
                                <td>Discount</td>
                                <td class="discount-val">−${{ number_format($order->discount_amount, 2) }}</td>
                            </tr>
                        @endif

                        {{-- Coupon discount (separate from general discount) --}}
                        @if(($order->coupon_discount ?? 0) > 0)
                            <tr>
                                <td>
                                    Coupon
                                    @if($order->coupon_code)
                                        <span style="font-size:10px;color:#bbb;">({{ $order->coupon_code }})</span>
                                    @endif
                                </td>
                                <td class="discount-val">−${{ number_format($order->coupon_discount, 2) }}</td>
                            </tr>
                        @endif

                        {{-- Shipping --}}
                        <tr>
                            <td>Shipping</td>
                            <td>${{ number_format($order->shipping_fee, 2) }}</td>
                        </tr>

                        {{-- Grand total = total_price, already computed in checkout
                             (subtotal − coupon discount + shipping). Do NOT
                             recalculate here — keep a single source of truth. --}}
                        <tr class="grand">
                            <td>Grand total</td>
                            <td>${{ number_format($order->total_price, 2) }}</td>
                        </tr>

                        {{-- Payment status --}}
                        <tr class="pmnt">
                            <td>Payment</td>
                            <td style="font-weight:bold;color:#1a6b30;">
                                {{ ucfirst(optional($order->payment)->status ?? 'Pending') }}
                            </td>
                        </tr>

                    </table>
                </div>
            </div>

            {{-- ══ FOOTER ══ --}}
            <div class="inv-footer">
                <div class="footer-left">
                    <div class="footer-ty">Thank you for shopping with Vendo.</div>
                    <div class="footer-gen">Generated on {{ now()->format('d M Y · h:i A') }}</div>
                </div>
                <div class="footer-right">
                    <div class="footer-brand">Vendo</div>
                </div>
            </div>

        </div>
        <div class="inv-stripe-bot"></div>
    </div>

</body>

</html>