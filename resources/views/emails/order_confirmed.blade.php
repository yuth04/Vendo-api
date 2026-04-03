<div style="background-color: #f3f4f6; padding: 40px 20px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">

        <!-- HEADER -->
        <div style="padding: 40px; border-bottom: 1px solid #f1f5f9;">
            <table width="100%">
                <tr>
                    <td>
                        <h1 style="margin: 0; color: #2b3a55; font-size: 28px;">🎉 Order Confirmed</h1>
                        <p style="margin: 5px 0 0; color: #94a3b8; font-size: 12px;">
                            Thank you for shopping with VENDO
                        </p>
                    </td>
                    <td style="text-align: right;">
                        <p style="margin: 0; font-weight: 700; color: #1e293b;">
                            Order #{{ $order->order_number }}
                        </p>
                        <p style="margin: 5px 0 0; color: #64748b;">
                            {{ $order->created_at->format('M d, Y') }}
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- CUSTOMER INFO -->
        <div style="padding: 30px 40px; background-color: #fafbfd;">
            <table width="100%">
                <tr>
                    <td width="50%">
                        <p style="margin: 0 0 10px; color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                            Shipping To
                        </p>

                        <p style="margin: 0; font-weight: 600; color: #1e293b;">
                            {{ $order->user->first_name }} {{ $order->user->last_name }}
                        </p>

                        <p style="margin: 5px 0; color: #64748b; font-size: 13px; line-height: 1.4;">
                            {{ $order->address->address_line ?? 'No address provided' }}<br>
                            {{ $order->address->city ?? '' }}, {{ $order->address->province ?? '' }}<br>
                            {{ $order->address->phone ?? '' }}
                        </p>
                    </td>

                    <td width="50%" style="text-align: right;">
                        <p style="margin: 0 0 10px; color: #94a3b8; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                            Payment Method
                        </p>

                        <p style="margin: 0; font-weight: 600;">
                            {{ $order->payment->payment_method ?? 'Online Payment' }}
                        </p>

                        <p style="margin: 5px 0; color: #10b981; font-size: 13px; font-weight: 600;">
                            Status: Confirmed
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
                        <th align="left" style="padding-bottom: 15px; border-bottom: 1px solid #e2e8f0;">Item</th>
                        <th align="center" style="padding-bottom: 15px; border-bottom: 1px solid #e2e8f0;">Qty</th>
                        <th align="right" style="padding-bottom: 15px; border-bottom: 1px solid #e2e8f0;">Total</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td style="padding: 20px 0; border-bottom: 1px solid #f1f5f9;">
                                <p style="margin: 0; font-weight: 600; color: #1e293b;">
                                    {{ $item->product_name }}
                                </p>
                                <p style="margin: 5px 0 0; color: #94a3b8; font-size: 12px;">
                                    {{ $item->size }} / {{ $item->color }}
                                </p>
                            </td>

                            <td align="center" style="color: #64748b;">
                                {{ $item->quantity }}
                            </td>

                            <td align="right" style="font-weight: 600; color: #1e293b;">
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
                        <td style="color: #64748b;">Subtotal</td>
                        <td align="right">${{ number_format($order->total_price - $shipping, 2) }}</td>
                    </tr>

                    <tr>
                        <td style="color: #64748b;">Shipping</td>
                        <td align="right">${{ number_format($shipping, 2) }}</td>
                    </tr>

                    <tr style="border-top: 1px solid #e2e8f0;">
                        <td style="font-size: 16px; font-weight: 700;">Total</td>
                        <td align="right" style="font-size: 18px; font-weight: 800;">
                            ${{ number_format($order->total_price, 2) }}
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- FOOTER -->
        <div style="padding: 0 40px 40px; text-align: center;">
            @if(!isset($isPdf) || !$isPdf)
                <a href="{{ $downloadUrl }}" target="_blank"
                   style="display: inline-block; background-color: #2b3a55; color: #fff; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-weight: 600;">
                    📥 Download Invoice
                </a>
            @endif

            <p style="margin: 20px 0 0; color: #94a3b8; font-size: 12px;">
                Thank you for your order 💙 If you have any questions, contact support@vendo.com
            </p>
        </div>

    </div>
</div>