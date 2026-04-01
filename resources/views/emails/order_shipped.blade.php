<div
    style="font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #f0f0f0; border-radius: 8px;">

    <div style="text-align: center; padding-bottom: 20px; border-bottom: 2px solid #f8f9fa;">
        <h2 style="color: #4f46e5; margin: 0;">Order Shipped!</h2>
    </div>

    <div style="padding: 30px 0;">
        <h1 style="font-size: 22px; color: #1a202c; margin-top: 0;">
            Hi, {{ $order->user->name }}!
        </h1>

        <p style="font-size: 16px; color: #4a5568;">
            Exciting news! Your order <span style="font-weight: bold; color: #2d3748;">#{{ $order->id }}</span> is on
            its way. Our team has handed it over to the courier.
        </p>

        <div style="background-color: #f0f5ff; border-left: 4px solid #4f46e5; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; font-size: 15px; color: #4338ca;">
                <strong>Current Status:</strong>
                <span style="text-transform: uppercase; letter-spacing: 1px;">In Transit (Shipping)</span>
            </p>
            <p style="margin: 5px 0 0; font-size: 14px; color: #5a67d8;">
                <strong>Shipped At:</strong> {{ $order->shipped_at }}
            </p>
        </div>

        <p style="font-size: 16px; color: #4a5568;">
            You can check your order history on our website for more details or to see any updates from the courier.
        </p>
    </div>

    <div style="text-align: center; font-size: 12px; color: #a0aec0; border-top: 1px solid #f0f0f0; padding-top: 20px;">
        <p style="margin: 0;">Thank you for shopping with us!</p>
        <p style="margin: 5px 0 0;">© {{ date('Y') }} Your Brand Name. All rights reserved.</p>
    </div>

</div>