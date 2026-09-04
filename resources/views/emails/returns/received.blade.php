```blade
<!DOCTYPE html>

<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta name="x-apple-disable-message-reformatting">

    <title>Return Request Update - VENDO</title>

    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI',
                Roboto, Helvetica, Arial, sans-serif;
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


                <!-- ===================================================== -->
                <!-- MAIN CONTAINER -->
                <!-- ===================================================== -->

                <table class="container" border="0" cellpadding="0" cellspacing="0" width="100%" style="
        max-width: 580px;
        background-color: #ffffff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    ">


                    <!-- ===================================================== -->
                    <!-- HEADER -->
                    <!-- ===================================================== -->

                    <tr>

                        <td style="padding: 48px 40px 32px; text-align: center;">

                            @php

                                $hasExchange = $returnOrder->items
                                    ->contains('return_type', 'exchange');

                                $hasRefund = $returnOrder->items
                                    ->contains('return_type', 'refund');

                            @endphp


                            <!-- Status Badge -->

                            <div style="margin-bottom: 20px;">

                                <span style="
        display: inline-block;

        background-color:
            {{ $hasExchange && !$hasRefund
    ? '#eff6ff'
    : '#f0fdf4'
            }};

        color:
            {{ $hasExchange && !$hasRefund
    ? '#1d4ed8'
    : '#15803d'
            }};

        padding: 10px 20px;

        border-radius: 99px;

        font-weight: 700;

        font-size: 13px;

        text-transform: uppercase;

        letter-spacing: 1px;
    ">

                                    @if($hasExchange && $hasRefund)

                                        Return Received

                                    @elseif($hasExchange)

                                        Exchange Received

                                    @else

                                        Return Received

                                    @endif

                                </span>

                            </div>


                            <!-- Heading -->

                            @if($hasExchange && $hasRefund)

                                <h1 style="
                                            margin: 0;
                                            color: #1e293b;
                                            font-size: 26px;
                                            font-weight: 800;
                                            letter-spacing: -0.02em;
                                        ">
                                    Your Return Has Been Received!
                                </h1>

                                <p style="
                                            margin: 12px 0 0;
                                            color: #64748b;
                                            font-size: 15px;
                                            line-height: 1.6;
                                        ">

                                    Hi

                                    <strong>
                                        {{ $returnOrder->user->first_name ?? $returnOrder->user->name ?? 'Customer' }}
                                    </strong>,

                                    your return request for Order

                                    <strong>
                                        #{{ $returnOrder->order->order_number ?? $returnOrder->order_id }}
                                    </strong>

                                    has been successfully received and is now being processed.

                                </p>


                            @elseif($hasExchange)

                                <h1 style="
                                            margin: 0;
                                            color: #1e293b;
                                            font-size: 26px;
                                            font-weight: 800;
                                            letter-spacing: -0.02em;
                                        ">
                                    Your Exchange Has Been Received!
                                </h1>

                                <p style="
                                            margin: 12px 0 0;
                                            color: #64748b;
                                            font-size: 15px;
                                            line-height: 1.6;
                                        ">

                                    Hi

                                    <strong>
                                        {{ $returnOrder->user->first_name ?? $returnOrder->user->name ?? 'Customer' }}
                                    </strong>,

                                    your exchange request for Order

                                    <strong>
                                        #{{ $returnOrder->order->order_number ?? $returnOrder->order_id }}
                                    </strong>

                                    has been successfully received and is now being processed.

                                </p>


                            @else

                                <h1 style="
                                            margin: 0;
                                            color: #1e293b;
                                            font-size: 26px;
                                            font-weight: 800;
                                            letter-spacing: -0.02em;
                                        ">
                                    Your Return Has Been Received!
                                </h1>

                                <p style="
                                            margin: 12px 0 0;
                                            color: #64748b;
                                            font-size: 15px;
                                            line-height: 1.6;
                                        ">

                                    Hi

                                    <strong>
                                        {{ $returnOrder->user->first_name ?? $returnOrder->user->name ?? 'Customer' }}
                                    </strong>,

                                    your return for Order

                                    <strong>
                                        #{{ $returnOrder->order->order_number ?? $returnOrder->order_id }}
                                    </strong>

                                    has been successfully received and is now being processed.

                                </p>

                            @endif

                        </td>

                    </tr>


                    <!-- ===================================================== -->
                    <!-- SUMMARY CARD -->
                    <!-- ===================================================== -->

                    <tr>

                        <td style="padding: 0 40px 32px;">

                            <table width="100%" border="0" cellspacing="0" cellpadding="0" style="
        background-color: #f8fafc;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
    ">

                                <tr>

                                    <td style="padding: 20px 24px;">


                                        <table width="100%" border="0" cellspacing="0" cellpadding="0">

                                            <tr>


                                                <!-- Received Date -->

                                                <td class="mobile-stack" style="padding-bottom: 8px;">

                                                    <div style="
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    ">
                                                        Received On
                                                    </div>

                                                    <div style="
        font-size: 14px;
        color: #1e293b;
        font-weight: 600;
    ">

                                                        {{ $returnOrder->received_at
    ? \Carbon\Carbon::parse($returnOrder->received_at)->format('d M Y, h:i A')
    : now()->format('d M Y, h:i A')
}}

                                                    </div>

                                                </td>


                                                <!-- Request Type -->

                                                <td align="right" class="mobile-stack">

                                                    <div style="
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 4px;
    ">
                                                        Request Type
                                                    </div>

                                                    <div style="
        font-size: 14px;
        color: #1e293b;
        font-weight: 700;
    ">

                                                        @if($hasExchange && $hasRefund)

                                                            MIXED RETURN

                                                        @elseif($hasExchange)

                                                            PRODUCT EXCHANGE

                                                        @else

                                                            REFUND

                                                        @endif

                                                    </div>

                                                </td>

                                            </tr>

                                        </table>


                                        <hr style="
        border: 0;
        border-top: 1px solid #e2e8f0;
        margin: 16px 0;
    ">


                                        <!-- Return Information -->

                                        <div style="
        font-size: 13px;
        color: #64748b;
        line-height: 1.8;
    ">

                                            <strong>Return Status:</strong>

                                            <span style="
        color: #16a34a;
        font-weight: 600;
    ">
                                                {{ ucfirst($returnOrder->status) }}
                                            </span>

                                            <br>

                                            <strong>Return Number:</strong>

                                            {{ $returnOrder->return_number }}

                                        </div>

                                    </td>

                                </tr>

                            </table>

                        </td>

                    </tr>


                    <!-- ===================================================== -->
                    <!-- ITEMS -->
                    <!-- ===================================================== -->

                    <tr>

                        <td style="padding: 0 40px 32px;">

                            <h4 style="
        margin: 0 0 16px;
        font-size: 13px;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        font-weight: 700;
    ">
                                Item Details
                            </h4>


                            <table width="100%" border="0" cellspacing="0" cellpadding="0">


                                @foreach($returnOrder->items as $item)

                                                            <tr>


                                                                <td style="
                                                                                                    padding: 12px 0;
                                                                                                    border-bottom: 1px solid #f1f5f9;
                                                                                                ">


                                                                    <!-- Product Name -->

                                                                    <div style="
                                                                                                    font-size: 14px;
                                                                                                    font-weight: 600;
                                                                                                    color: #1e293b;
                                                                                                ">

                                                                        {{ $item->orderItem->product->name
                                    ?? $item->orderItem->productVariant->product->name
                                    ?? 'Returned Product'
                                                                                            }}

                                                                    </div>


                                                                    <!-- Quantity -->

                                                                    <div style="
                                                                                                    font-size: 12px;
                                                                                                    color: #64748b;
                                                                                                    margin-top: 3px;
                                                                                                ">

                                                                        Returned Qty:
                                                                        {{ $item->quantity }}

                                                                    </div>


                                                                    <!-- Return Type -->

                                                                    <div style="
                                                                                                    margin-top: 6px;
                                                                                                    font-size: 12px;
                                                                                                    font-weight: 700;
                                                                                                ">

                                                                        @if($item->return_type === 'exchange')

                                                                            <span style="color: #2563eb;">
                                                                                EXCHANGE
                                                                            </span>

                                                                        @else

                                                                            <span style="color: #16a34a;">
                                                                                REFUND
                                                                            </span>

                                                                        @endif

                                                                    </div>


                                                                    <!-- ================================================= -->
                                                                    <!-- EXCHANGE INFORMATION -->
                                                                    <!-- ================================================= -->

                                                                    @if(
                                                                            $item->return_type === 'exchange'
                                                                        )

                                                                        <div style="
                                                                                                                                                    margin-top: 8px;
                                                                                                                                                    padding: 10px 12px;
                                                                                                                                                    background-color: #f1f5f9;
                                                                                                                                                    border-radius: 6px;
                                                                                                                                                    font-size: 12px;
                                                                                                                                                    color: #334155;
                                                                                                                                                ">


                                                                            @if($item->replacementVariant)

                                                                                                            <strong>
                                                                                                                Replacement:
                                                                                                            </strong>

                                                                                                            {{ $item->replacementVariant->product->name
                                                                                ?? 'Replacement Product'
                                                                                                                                                                                                                                                            }}

                                                                                                            <br>

                                                                                                            @if($item->replacementVariant->size)

                                                                                                                <strong>
                                                                                                                    Size:
                                                                                                                </strong>

                                                                                                                {{ $item->replacementVariant->size }}

                                                                                                            @endif


                                                                                                            @if($item->replacementVariant->color)

                                                                                                                &nbsp;

                                                                                                                <strong>
                                                                                                                    Color:
                                                                                                                </strong>

                                                                                                                {{ $item->replacementVariant->color }}

                                                                                                            @endif

                                                                                                            <br>

                                                                            @else

                                                                                <strong>
                                                                                    Replacement Variant:
                                                                                </strong>

                                                                                #{{ $item->replacement_variant_id }}

                                                                                <br>

                                                                            @endif


                                                                            @if($item->exchange_quantity)

                                                                                <strong>
                                                                                    Exchange Qty:
                                                                                </strong>

                                                                                {{ $item->exchange_quantity }}

                                                                            @endif


                                                                            @if(
                                                                                                                (float) ($item->exchange_price_difference ?? 0) < 0
                                                                                                            )

                                                                                                            <div
                                                                                                                style="
                                                                                                                                                                                                                                                                    color: #16a34a;
                                                                                                                                                                                                                                                                    font-weight: 600;
                                                                                                                                                                                                                                                                    margin-top: 4px;
                                                                                                                                                                                                                                                                ">

                                                                                                                Partial Refund Due:

                                                                                                                ${{
                                                                                number_format(
                                                                                    abs($item->exchange_price_difference),
                                                                                    2
                                                                                )
                                                                                                                                                                                                                                                            }}

                                                                                                            </div>


                                                                            @elseif(
                                                                                                                (float) ($item->exchange_price_difference ?? 0) > 0
                                                                                                            )

                                                                                                            <div
                                                                                                                style="
                                                                                                                                                                                                                                                                    color: #d97706;
                                                                                                                                                                                                                                                                    font-weight: 600;
                                                                                                                                                                                                                                                                    margin-top: 4px;
                                                                                                                                                                                                                                                                ">

                                                                                                                Additional Charge:

                                                                                                                ${{
                                                                                number_format(
                                                                                    $item->exchange_price_difference,
                                                                                    2
                                                                                )
                                                                                                                                                                                                                                                            }}

                                                                                                            </div>

                                                                            @endif

                                                                        </div>

                                                                    @endif


                                                                    <!-- ================================================= -->
                                                                    <!-- REFUND INFORMATION -->
                                                                    <!-- ================================================= -->

                                                                    @if(
                                                                                                        $item->return_type === 'refund'
                                                                                                        && $item->refund
                                                                                                    )

                                                                                                    <div
                                                                                                        style="
                                                                                                                                                                                                                margin-top: 8px;
                                                                                                                                                                                                                padding: 10px 12px;
                                                                                                                                                                                                                background-color: #f0fdf4;
                                                                                                                                                                                                                border-radius: 6px;
                                                                                                                                                                                                                font-size: 12px;
                                                                                                                                                                                                                color: #334155;
                                                                                                                                                                                                            ">

                                                                                                        <strong>
                                                                                                            Refund Status:
                                                                                                        </strong>

                                                                                                        <span
                                                                                                            style="
                                                                                                                                                                                                                color: #16a34a;
                                                                                                                                                                                                                font-weight: 600;
                                                                                                                                                                                                            ">

                                                                                                            {{ strtoupper($item->refund->status) }}

                                                                                                        </span>

                                                                                                        <br>

                                                                                                        <strong>
                                                                                                            Refund Amount:
                                                                                                        </strong>

                                                                                                        ${{
                                                                        number_format(
                                                                            $item->refund->amount,
                                                                            2
                                                                        )
                                                                                                                                                                                                        }}

                                                                                                    </div>

                                                                    @endif


                                                                </td>


                                                                <!-- ================================================= -->
                                                                <!-- ITEM STATUS -->
                                                                <!-- ================================================= -->

                                                                <td align="right" style="
                                                                                                    padding: 12px 0;
                                                                                                    border-bottom: 1px solid #f1f5f9;
                                                                                                    font-size: 13px;
                                                                                                    font-weight: 600;
                                                                                                    color: #1e293b;
                                                                                                    vertical-align: top;
                                                                                                    white-space: nowrap;
                                                                                                ">


                                                                    @if($item->return_type === 'exchange')

                                                                        @if($item->exchange)

                                                                            {{ ucfirst($item->exchange->status) }}

                                                                        @else

                                                                            Received

                                                                        @endif


                                                                    @elseif($item->return_type === 'refund')

                                                                        @if($item->refund)

                                                                            {{ ucfirst($item->refund->status) }}

                                                                        @else

                                                                            Received

                                                                        @endif

                                                                    @endif


                                                                </td>

                                                            </tr>

                                @endforeach


                            </table>

                        </td>

                    </tr>


                    <!-- ===================================================== -->
                    <!-- FOOTER -->
                    <!-- ===================================================== -->

                    <tr>

                        <td style="
        padding: 32px 40px;
        background-color: #fafafa;
        text-align: center;
        border-top: 1px solid #f1f5f9;
    ">


                            <p style="
        margin: 0 0 16px;
        color: #1e293b;
        font-weight: 600;
        font-size: 14px;
    ">
                                Questions about your return?
                            </p>


                            <a href="{{ url('/support') }}" style="
        display: inline-block;
        background-color: #1e293b;
        color: #ffffff;
        padding: 12px 28px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 700;
        font-size: 13px;
    ">
                                Visit Support Center
                            </a>


                            <p style="
        margin: 28px 0 0;
        color: #94a3b8;
        font-size: 12px;
        line-height: 1.6;
    ">

                                Thank you for shopping with
                                <strong>VENDO</strong> ❤️

                                <br>

                                We look forward to serving you again.

                            </p>

                        </td>

                    </tr>


                </table>


                <!-- ===================================================== -->
                <!-- COPYRIGHT -->
                <!-- ===================================================== -->

                <table width="100%" border="0" cellspacing="0" cellpadding="0" style="max-width: 580px;">

                    <tr>

                        <td style="
        padding: 24px 30px;
        text-align: center;
        color: #94a3b8;
        font-size: 11px;
    ">

                            &copy; {{ date('Y') }} VENDO.
                            All rights reserved.

                            <br>

                            Phnom Penh, Cambodia.

                        </td>

                    </tr>

                </table>


            </td>

        </tr>

    </table>

</body>

</html>
```