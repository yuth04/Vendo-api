<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>Return Invoice - {{ $return->return_number ?? $return->id }}</title>

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

        .store-info {
            color: #666;
            line-height: 1.6;
        }

        .invoice-title {
            text-align: right;
            font-size: 25px;
            font-weight: bold;
        }

        .invoice-number {
            text-align: right;
            margin-top: 8px;
            color: #666;
        }

        .divider {
            border-top: 2px solid #333;
            margin: 20px 0;
        }

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
            margin-bottom: 6px;
        }

        .label {
            color: #777;
            display: inline-block;
            width: 120px;
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
        }

        .items-table td {
            border: 1px solid #ddd;
            padding: 9px;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .summary-wrapper {
            width: 100%;
            margin-top: 20px;
        }

        .summary-table {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 7px;
        }

        .summary-table .total {
            border-top: 2px solid #333;
            font-size: 15px;
            font-weight: bold;
        }

        .return-box {
            margin-top: 25px;
            padding: 15px;
            border: 1px solid #ddd;
        }

        .return-box-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .exchange-section {
            margin-top: 25px;
        }

        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #777;
            font-size: 10px;
        }

        .status {
            font-weight: bold;
            text-transform: uppercase;
        }

        .good {
            color: #198754;
        }

        .damaged {
            color: #dc3545;
        }
    </style>
</head>

<body>

    <div class="invoice">

        {{-- HEADER --}}
        <div class="header">
            <table class="header-table">
                <tr>
                    <td>
                        <div class="store-name">
                            VENDO STORE
                        </div>

                        <div class="store-info">
                            Online Shopping Platform<br>
                            Cambodia<br>
                            Email: support@vendo.com
                        </div>
                    </td>

                    <td>
                        <div class="invoice-title">
                            RETURN INVOICE
                        </div>

                        <div class="invoice-number">
                            Return No:
                            <strong>
                                {{ $return->return_number ?? 'RET-' . str_pad($return->id, 5, '0', STR_PAD_LEFT) }}
                            </strong>
                            <br>

                            Date:
                            {{ optional($return->created_at)->format('d M Y') }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="divider"></div>

        {{-- CUSTOMER / ORDER INFORMATION --}}
        <table class="info-table">
            <tr>

                <td>
                    <div class="info-title">
                        Customer Information
                    </div>

                    <div>
                        <span class="label">Name:</span>
                        {{ $return->user->first_name ?? '' }} {{ $return->user->last_name ?? '' }}
                    </div>

                    <div>
                        <span class="label">Email:</span>
                        {{ $return->user->email ?? 'N/A' }}
                    </div>
                </td>

                <td>
                    <div class="info-title">
                        Order Information
                    </div>

                    <div>
                        <span class="label">Order No:</span>
                        {{ $return->order->order_number ?? $return->order_id }}
                    </div>

                    <div>
                        <span class="label">Return Type:</span>
                        {{ ucfirst($return->return_type ?? 'refund') }}
                    </div>

                    <div>
                        <span class="label">Return Date:</span>
                        {{ optional($return->created_at)->format('d M Y') }}
                    </div>

                    <div>
                        <span class="label">Status:</span>
                        <span class="status">
                            {{ ucfirst($return->status) }}
                        </span>
                    </div>
                </td>

            </tr>
        </table>

        {{-- RETURN ITEMS --}}
        <div class="info-title">
            Returned Items
        </div>

        <table class="items-table">

            <thead>
                <tr>
                    <th style="width: 35%;">
                        Product
                    </th>

                    <th style="width: 20%;">
                        Variant
                    </th>

                    <th style="width: 10%;" class="text-center">
                        Qty
                    </th>

                    <th style="width: 15%;" class="text-right">
                        Unit Price
                    </th>

                    <th style="width: 20%;" class="text-right">
                        Amount
                    </th>
                </tr>
            </thead>

            <tbody>

                @foreach($return->items as $item)

                    @php
                        $quantity = $item->quantity ?? 1;

                        $unitPrice =
                            $item->orderItem->price
                            ?? $item->orderItem->unit_price
                            ?? 0;

                        $amount = $quantity * $unitPrice;

                        $variant = $item->variant
                            ?? $item->orderItem->variant
                            ?? $item->orderItem->productVariant
                            ?? null;
                    @endphp

                    <tr>

                        <td>
                            {{ $item->orderItem->product_name ?? 'N/A' }}
                        </td>

                        <td>

                            <div class="variant">

                                @if($variant->size || $variant->color)

                                    @if($variant->color)

                                    @endif

                                    @if($variant->color)
                                        {{ $variant->color }}
                                    @endif
                                    /
                                    @if($variant->size)
                                        {{ $variant->size }}
                                    @endif


                                @elseif($item->variant)

                                                    {{ $item->variant->name
                                    ?? $item->variant->variant_name
                                    ?? $item->variant->sku
                                    ?? 'N/A'
                                                                                                                                                                }}

                                @else

                                    N/A

                                @endif

                            </div>

                        </td>


                        </td></span>
                        </td>

                        <td class="text-center">
                            {{ $quantity }}
                        </td>

                        <td class="text-right">
                            ${{ number_format($unitPrice, 2) }}
                        </td>

                        <td class="text-right">
                            ${{ number_format($amount, 2) }}
                        </td>

                    </tr>

                @endforeach

            </tbody>

        </table>

        {{-- INSPECTION --}}
        @if($return->items->count())

            <div class="return-box">

                <div class="return-box-title">
                    Return Inspection
                </div>

                @foreach($return->items as $item)

                    <div style="margin-bottom: 7px;">

                        <strong>
                            {{ $item->orderItem->product->name ?? 'Product' }}
                        </strong>

                        :

                        @if(isset($item->condition))

                            @if($item->condition === 'good')

                                <span class="good">
                                    Good Condition
                                </span>

                            @elseif($item->condition === 'damaged')

                                <span class="damaged">
                                    Damaged
                                </span>

                            @else

                                {{ ucfirst($item->condition) }}

                            @endif

                        @else
                            Not Inspected
                        @endif

                    </div>

                @endforeach

            </div>

        @endif

        {{-- REFUND INFORMATION --}}
        @if(($return->return_type ?? 'refund') === 'refund')

                <div class="return-box">

                    <div class="return-box-title">
                        Refund Information
                    </div>

                    <div>
                        <span class="label">
                            Refund Method:
                        </span>

                        {{ ucwords(str_replace(
                '_',
                ' ',
                $return->refund_method ?? 'N/A'
            )) }}
                    </div>

                    <div>
                        <span class="label">
                            Refund Status:
                        </span>

                        <span class="status">
                            {{ ucfirst($return->refund_status ?? 'Pending') }}
                        </span>
                    </div>

                </div>

        @endif

        {{-- EXCHANGE INFORMATION --}}
        @if(($return->return_type ?? null) === 'exchange')

            <div class="return-box exchange-section">

                <div class="return-box-title">
                    Exchange Information
                </div>

                @foreach($return->items as $item)

                    @if(isset($item->replacementVariant))

                        <div style="margin-bottom: 8px;">

                            <strong>
                                {{ $item->orderItem->product->name ?? 'Product' }}
                            </strong>

                            <br>

                            Returned Variant:
                            {{ $item->variant->name
                            ?? $item->variant->variant_name
                            ?? $item->variant->sku
                            ?? 'N/A'
                                                                                                            }}

                            <br>

                            Replacement Variant:
                            {{ $item->replacementVariant->name
                            ?? $item->replacementVariant->variant_name
                            ?? $item->replacementVariant->sku
                            ?? 'N/A'
                                                                                                            }}

                        </div>

                    @endif

                @endforeach

            </div>

        @endif

        {{-- SUMMARY --}}
        @php

            $subtotal = 0;

            foreach ($return->items as $item) {

                $quantity = $item->quantity ?? 1;

                $unitPrice =
                    $item->orderItem->price
                    ?? $item->orderItem->unit_price
                    ?? 0;

                $subtotal += $quantity * $unitPrice;
            }

            $refundAmount =
                $return->refund_amount
                ?? $subtotal;

        @endphp

        <div class="summary-wrapper">

            <table class="summary-table">

                <tr>
                    <td>
                        Subtotal
                    </td>

                    <td class="text-right">
                        ${{ number_format($subtotal, 2) }}
                    </td>
                </tr>

                @if(($return->return_type ?? 'refund') === 'refund')

                    <tr class="total">

                        <td>
                            REFUND TOTAL
                        </td>

                        <td class="text-right">
                            ${{ number_format($refundAmount, 2) }}
                        </td>

                    </tr>

                @else

                    <tr class="total">

                        <td>
                            EXCHANGE TOTAL
                        </td>

                        <td class="text-right">
                            $0.00
                        </td>

                    </tr>

                @endif

            </table>

        </div>

        {{-- FOOTER --}}
        <div class="footer">

            Thank you for shopping with VENDO STORE.

            <br>

            This document is a return transaction record.

            <br><br>

            Generated on
            {{ now()->format('d M Y H:i') }}

        </div>

    </div>

</body>

</html>