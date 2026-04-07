<div
    style="font-family: Helvetica, Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px; border: 1px solid #eee; border-radius: 8px;">

    <!-- HEADER -->
    <div style="text-align: center; border-bottom: 2px solid #f0f0f0; padding-bottom: 20px;">
        <h2 style="color: #10b981;">🎉 Order Delivered</h2>
    </div>

    <!-- CONTENT -->
    <div style="padding: 20px 0;">
        <h3>
            Hi {{ $order->user->first_name }} {{ $order->user->last_name }},
        </h3>

        <p>
            Great news! Your order
            <strong>#{{ $order->order_number ?? $order->id }}</strong>
            has been successfully delivered.
        </p>

        <div style="background:#ecfdf5; padding:15px; border-left:4px solid #10b981; margin:20px 0;">
            <p><strong>Status:</strong> Delivered</p>
            <p>
                <strong>Delivered At:</strong>
                {{ $order->delivered_at ? \Carbon\Carbon::parse($order->delivered_at)->format('d M Y, h:i A') : 'N/A' }}
            </p>
            <h4
                style="margin: 30px 0 10px; color: #111827; text-transform: uppercase; font-size: 12px; letter-spacing: 1px;">
                Payment Information</h4>
            <table border="0" cellpadding="0" cellspacing="0" width="100%"
                style="background-color: #f9fafb; border-radius: 6px; margin-bottom: 20px;">
                <tr>
                    <td style="padding: 15px;">
                        <p style="margin: 0; font-size: 12px; color: #4b5563; line-height: 1.8;">
                            <strong>Payment Method:</strong> {{ ucfirst($order->payment->payment_method) }} <br>
                            <strong>Transaction ID:</strong> <code
                                style="color: #111827;">{{ $transaction_id ?? 'N/A' }}</code><br>
                            <strong>Reference #:</strong> <code
                                style="color: #111827;">{{ $payment_reference ?? 'N/A' }}</code><br>
                            <strong>Payment Status:</strong> <span
                                style="color: #10b981; font-weight: bold;">Paid</span>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ORDER SUMMARY -->
        <h4 style="margin-top: 30px;">Order Summary</h4>

        <table width="100%" style="border-collapse: collapse;">
            <thead>
                <tr​​>
                    <th align="left">Product</th>
                    <th align="center">Qty</th>
                    <th align="right">Total</th>
                </tr​​>
            </thead>

            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td style="padding: 10px 0;">
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

        <!-- TOTAL -->
        <div style="text-align: right; margin-top: 20px;">
            <p><strong>Shipping:</strong> ${{ number_format($order->shipping_fee, 2) }}</p>
            <p style="font-size: 18px;">
                <strong>Total: ${{ number_format($order->total_price, 2) }}</strong>
            </p>
        </div>

        <p style="text-align: center;margin-top: 20px;">
            Thank you for shopping with us ❤️<br>
            We hope you enjoy your purchase!
        </p>
    </div>

    <!-- FOOTER -->
    <div style="text-align:center; font-size:12px; color:#999; border-top:1px solid #eee; padding-top:15px;">
        <p>© {{ date('Y') }} Your Brand</p>
    </div>

</div>