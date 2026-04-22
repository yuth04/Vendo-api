<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Shipped - #{{ $order->order_number }}</title>
</head>

<body style="margin:0; padding:0; background:#f8fafc; font-family:Arial, sans-serif;">
<table align="center" width="100%" cellpadding="0" cellspacing="0">
<tr>
<td align="center">

<table width="100%" style="max-width:600px; background:#ffffff; border-radius:12px; overflow:hidden;">

    <!-- HEADER -->
    <tr>
        <td style="padding:40px; border-bottom:1px solid #eee;">
            <table width="100%">
                <tr>
                    <td>
                        <h2 style="margin:0; color:#2b3a55;">Order Shipped</h2>
                        <p style="margin:5px 0 0; color:#888;">
                            Hi {{ $order->user->first_name }}, your order is on the way!
                        </p>
                    </td>
                    <td align="right">
                        <strong>#{{ $order->order_number }}</strong>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- STATUS -->
    <tr>
        <td style="padding:20px 40px;">
            <div style="background:#eff6ff; padding:15px; border-radius:8px; text-align:center;">
                <strong style="color:#1d4ed8;">Status: SHIPPED</strong><br>
                <span style="font-size:13px; color:#555;">
                    Dispatched at 
                    {{ $order->shipped_at ? \Carbon\Carbon::parse($order->shipped_at)->format('d M Y, h:i A') : 'Today' }}
                </span>
            </div>
        </td>
    </tr>

    <!-- SHIPPING + PAYMENT -->
    <tr>
        <td style="padding:0 40px 30px;">
            <table width="100%">
                <tr>
                    <td width="50%">
                        <strong>Shipping Address</strong><br>
                        {{ $order->user->first_name }} {{ $order->user->last_name }}<br>
                        {{ $order->address->address_line ?? '' }}<br>
                        {{ $order->address->city }}, {{ $order->address->province }}
                    </td>

                    <td width="50%" align="right">
                        <strong>Payment</strong><br>
                        {{ $order->payment->payment_method ?? 'COD' }}<br>
                        <span style="color:#10b981;">
                            {{ $order->payment->status ?? 'Pending' }}
                        </span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- ITEMS -->
    <tr>
        <td style="padding:0 40px;">
            <table width="100%">
                <thead>
                    <tr>
                        <th align="left">Item</th>
                        <th align="center">Qty</th>
                        <th align="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                    <tr>
                        <td style="padding:10px 0;">
                            {{ $item->product_name }}<br>
                            <small>{{ $item->size }} / {{ $item->color }}</small>
                        </td>
                        <td align="center">{{ $item->quantity }}</td>
                        <td align="right">
                            ${{ number_format($item->price * $item->quantity, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>

    <!-- TOTAL -->
    <tr>
        <td style="padding:30px 40px;">
            <table align="right" width="220">
                <tr>
                    <td>Subtotal</td>
                    <td align="right">
                        ${{ number_format($order->total_price - $order->shipping_fee, 2) }}
                    </td>
                </tr>
                <tr>
                    <td>Shipping</td>
                    <td align="right">
                        ${{ number_format($order->shipping_fee, 2) }}
                    </td>
                </tr>
                <tr>
                    <td><strong>Total</strong></td>
                    <td align="right"><strong>
                        ${{ number_format($order->total_price, 2) }}
                    </strong></td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- CTA -->
    <tr>
        <td align="center" style="padding:30px;">
            <a href="https://vendo-ecommerce-two.vercel.app/account/orders"
               style="background:#2b3a55; color:#fff; padding:12px 25px; text-decoration:none; border-radius:6px;">
                Track Order
            </a>
        </td>
    </tr>

    <!-- FOOTER -->
    <tr>
        <td style="padding:20px; text-align:center; font-size:12px; color:#999;">
            Thank you for shopping with VENDO ❤️<br>
            Phnom Penh, Cambodia
        </td>
    </tr>

</table>

</td>
</tr>
</table>
</body>
</html>