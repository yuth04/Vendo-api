<<<<<<< HEAD
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
=======
<div style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #f0f0f0; border-radius: 8px;">
    
    <div style="text-align: center; padding-bottom: 20px; border-bottom: 2px solid #f8f9fa;">
        <h2 style="color: #2d3748; margin: 0;">Order Confirmation</h2>
    </div>

    <div style="padding: 30px 0;">
        <h1 style="font-size: 22px; color: #1a202c; margin-top: 0;">
            Hello, {{ $order->user->name }}!
        </h1>
        
        <p style="font-size: 16px; color: #4a5568;">
            Good news! Your order <span style="font-weight: bold; color: #2d3748;">#{{ $order->id }}</span> has been confirmed and is now being processed.
        </p>

        <div style="background-color: #ebf8ff; border-left: 4px solid #4299e1; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; font-size: 15px; color: #2b6cb0;">
                <strong>Current Status:</strong> 
                <span style="text-transform: uppercase; letter-spacing: 1px;">{{ $order->status }}</span>
            </p>
        </div>

        <p style="font-size: 16px; color: #4a5568;">
            We'll notify you as soon as your items are on their way.
        </p>
    </div>

    <div style="text-align: center; font-size: 12px; color: #a0aec0; border-top: 1px solid #f0f0f0; padding-top: 20px;">
        <p style="margin: 0;">Thank you for shopping with us!</p>
        <p style="margin: 5px 0 0;">© {{ date('Y') }} Your Store Name. All rights reserved.</p>
    </div>

>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
</div>