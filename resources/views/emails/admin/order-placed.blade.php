<div style="background-color: #f3f4f6; padding: 40px 20px; font-family: Helvetica, Arial, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden;">

        <!-- HEADER -->
        <div style="padding: 40px; border-bottom: 1px solid #f1f5f9;">
            <table width="100%">
                <tr>
                    <td>
                        <h1 style="margin: 0; color: #2b3a55; font-size: 26px;">🛒 New Order Alert</h1>
                        <p style="margin: 5px 0 0; color: #94a3b8; font-size: 12px;">
                            Admin Notification
                        </p>
                    </td>
                    <td style="text-align: right;">
                        <p style="margin: 0; font-weight: 700;">
                            Order #{{ $order->order_number }}
                        </p>
                        <p style="margin: 5px 0 0; color: #64748b;">
                            {{ $order->created_at->format('M d, Y h:i A') }}
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- CUSTOMER INFO -->
        <div style="padding: 30px 40px; background-color: #fafbfd;">
            <p style="margin: 0 0 10px; font-size: 11px; color: #94a3b8; text-transform: uppercase;">
                Customer Information
            </p>

            <p style="margin: 0; font-weight: 600;">
                {{ $order->user->first_name }} {{ $order->user->last_name }}
            </p>

            <p style="margin: 5px 0; color: #64748b;">
                {{ $order->address->phone ?? '' }}
            </p>

            <p style="margin: 5px 0; color: #64748b;">
                {{ $order->address->address_line ?? '' }},
                {{ $order->address->city ?? '' }},
                {{ $order->address->province ?? '' }}
            </p>
        </div>

        <!-- ORDER ITEMS -->
        <div style="padding: 40px;">
            <table width="100%" style="border-collapse: collapse;">
                <thead>
                    <tr>
                        <th align="left">Product</th>
                        <th align="center">Qty</th>
                        <th align="right">Total</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td style="padding: 15px 0;">
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
            <div style="margin-top: 30px; text-align: right;">
                 <p style="margin: 0; font-size: 14px; font-weight: 700;">
                    Shipping: ${{ number_format($order->shipping_fee, 2) }}
                </p>
                <p style="margin: 0; font-size: 16px; font-weight: 700;">
                    Total: ${{ number_format($order->total_price, 2) }}
                </p>
            </div>
        </div>

        <!-- FOOTER -->
        <div style="padding: 20px 40px; text-align: center; background: #fafbfd;">
            <p style="margin: 0; color: #64748b; font-size: 12px;">
                ⚠️ Please process this order in the admin dashboard
            </p>
        </div>

    </div>
</div>