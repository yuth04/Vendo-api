<div style="font-family: Helvetica, Arial, sans-serif; max-width: 600px; margin: auto; padding: 20px;">

    <div style="text-align: center; border-bottom: 2px solid #f0f0f0; padding-bottom: 20px;">
        <h2 style="color: #4f46e5;">🚚 Your Order is On the Way!</h2>
    </div>

    <div style="padding: 20px 0;">
        <h3>
            Hi {{ $order->user->first_name }} {{ $order->user->last_name }},
        </h3>

        <p>
            Good news! Your order <strong>#{{ $order->order_number ?? $order->id }}</strong> has been shipped.
        </p>

        <div style="background:#f0f5ff; padding:15px; border-left:4px solid #4f46e5; margin:15px 0;">
            <p><strong>Status:</strong> Shipped / In Transit</p>

            <p>
                <strong>Shipped At:</strong>
                {{ $order->shipped_at ? \Carbon\Carbon::parse($order->shipped_at)->format('d M Y, h:i A') : 'N/A' }}
            </p>
        </div>

        <p>
            You can track your order anytime from your account dashboard.
        </p>
    </div>

    <div style="text-align:center; font-size:12px; color:#999; border-top:1px solid #eee; padding-top:15px;">
        <p>Thank you for shopping with us ❤️</p>
        <p>© {{ date('Y') }} Your Brand</p>
    </div>

</div>