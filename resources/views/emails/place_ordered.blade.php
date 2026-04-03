<div
    style="background-color: #f3f4f6; padding: 40px 20px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <div
        style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">

        <!-- HEADER -->
        <div style="padding: 40px; border-bottom: 1px solid #f1f5f9;">
            <table width="100%">
                <tr>
                    <td>
                        <h1 style="margin: 0; color: #2b3a55; font-size: 28px;">🛒 Order Placed</h1>
                        <p style="margin: 5px 0 0; color: #94a3b8; font-size: 12px;">
                            We’ve received your order
                        </p>
                    </td>
                    <td style="text-align: right;">
                        <p style="margin: 0; font-weight: 700;">
                            Order #{{ $order->order_number }}
                        </p>
                        <p style="margin: 5px 0 0; color: #64748b;">
                            {{ $order->created_at->format('M d, Y') }}
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- STATUS NOTICE -->
        <div style="padding: 20px 40px; background-color: #fff7ed;">
            <p style="margin: 0; color: #9a3412; font-weight: 600;">
                ⏳ Your order is currently pending confirmation
            </p>
            <p style="margin: 5px 0 0; color: #64748b; font-size: 13px;">
                We will notify you once your order is confirmed and being processed.
            </p>
        </div>

        <!-- CUSTOMER INFO -->
        <div style="padding: 30px 40px; background-color: #fafbfd;">
            <table width="100%">
                <tr>
                    <td width="50%">
                        <p style="margin: 0 0 10px; font-size: 11px; color: #94a3b8; text-transform: uppercase;">
                            Shipping To
                        </p>

                        <p style="margin: 0; font-weight: 600;">
                            {{ $order->user->first_name }} {{ $order->user->last_name }}
                        </p>

                        <p style="margin: 5px 0; color: #64748b; font-size: 13px;">
                            {{ $order->address->address_line ?? '' }},
                            {{ $order->address->city ?? '' }},
                            {{ $order->address->province ?? '' }}
                        </p>

                        <p style="margin: 5px 0; color: #64748b;">
                            {{ $order->address->phone ?? '' }}
                        </p>
                    </td>

                    <td width="50%" style="text-align: right;">
                        <p style="margin: 0 0 10px; font-size: 11px; color: #94a3b8; text-transform: uppercase;">
                            Payment Method
                        </p>

                        <p style="margin: 0; font-weight: 600;">
                            {{ $order->payment->payment_method ?? 'Online Payment' }}
                        </p>

                        <p style="margin: 5px 0; color: #f59e0b; font-weight: 600;">
                            Status: Pending
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ITEMS -->
        <div style="padding: 40px;">
            <table width="100%" style="border-collapse: collapse;">
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
                            <td style="padding: 20px 0;">
                                <strong>{{ $item->product_name }}</strong><br>
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
            <div style="margin-top: 30px; width: 250px; margin-left: auto;">
                <table width="100%">
                    <tr>
                        <td>Subtotal</td>
                        <td align="right">${{ number_format($order->total_price - 1.00, 2) }}</td>
                    </tr>

                    <tr>
                        <td>Shipping</td>
                        <td align="right">${{ number_format($order->shipping_fee, 2) }}</td>
                    </tr>

                    <tr style="border-top: 1px solid #e2e8f0;">
                        <td style="font-weight: 700;">Total</td>
                        <td align="right" style="font-weight: 800;">
                            ${{ number_format($order->total_price, 2) }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- FOOTER -->
        <div style="padding: 0 40px 40px; text-align: center;">
   

            <p style="margin: 20px 0 0; color: #94a3b8; font-size: 12px;">
                We’ll notify you once your order is confirmed. Thank you for shopping with us 💙
            </p>
        </div>

    </div>
</div>