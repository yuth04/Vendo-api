<div style="background-color: #f1f5f9; padding: 40px 10px; font-family: 'Inter', system-ui, sans-serif;">
    <div
        style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;">

        <div style="background-color: #1e293b; padding: 25px; text-align: center;">
            <span
                style="background: #38bdf8; color: #1e293b; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;">Action
                Required</span>
            <h2 style="margin: 10px 0 0; color: #ffffff; font-size: 22px; font-weight: 700;">New Return Request</h2>
        </div>

        <div style="padding: 30px;">
            <div
                style="display: flex; align-items: center; margin-bottom: 25px; padding: 15px; background: #f8fafc; border-radius: 10px;">
                <div style="flex: 1;">
                    <p style="margin: 0; color: #64748b; font-size: 12px; text-transform: uppercase;">Customer</p>
                    <p style="margin: 0; color: #0f172a; font-weight: 600; font-size: 16px;">
                        {{ $returnOrder->user->first_name }} {{ $returnOrder->user->last_name }}</p>
                </div>
                <div style="text-align: right;">
                    <p style="margin: 0; color: #64748b; font-size: 12px; text-transform: uppercase;">Order ID</p>
                    <p style="margin: 0; color: #0f172a; font-weight: 600;">#{{ $returnOrder->order_id }}</p>
                </div>
            </div>

            <p
                style="margin: 0 0 10px; color: #64748b; font-size: 12px; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;">
                Items for Return</p>
            <div style="border: 1px solid #f1f5f9; border-radius: 8px; overflow: hidden; margin-bottom: 25px;">
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <thead style="background-color: #f8fafc;">
                        <tr>
                            <th align="left"
                                style="padding: 10px 15px; font-size: 12px; color: #94a3b8; border-bottom: 1px solid #f1f5f9;">
                                Product</th>
                            <th align="center"
                                style="padding: 10px 15px; font-size: 12px; color: #94a3b8; border-bottom: 1px solid #f1f5f9;">
                                Qty</th>
                            <th align="right"
                                style="padding: 10px 15px; font-size: 12px; color: #94a3b8; border-bottom: 1px solid #f1f5f9;">
                                Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Loop through the 'items' collection defined in your ReturnOrder model --}}
                        @foreach($returnOrder->items as $item)
                            <tr>
                                <td style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9;">
                                    <div style="color: #1e293b; font-weight: 600; font-size: 14px;">
                                        {{-- Access the product name through the nested relationship --}}
                                        {{ $item->orderItem->product->name ?? 'Product Name Missing' }}
                                    </div>
                                    <div style="color: #94a3b8; font-size: 12px;">
                                        {{-- If you have attributes on your OrderItem model --}}
                                        @if(isset($item->orderItem->color) || isset($item->orderItem->size))
                                            {{ $item->orderItem->color }} {{ $item->orderItem->size }}
                                        @else
                                            Default Variant
                                        @endif
                                    </div>
                                </td>
                                <td align="center"
                                    style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #475569; font-size: 14px;">
                                    {{ $item->quantity }}
                                </td>
                                <td align="right"
                                    style="padding: 12px 15px; border-bottom: 1px solid #f1f5f9; color: #1e293b; font-weight: 600; font-size: 14px;">
                                    {{-- Pull the price from the original OrderItem --}}
                                    ${{ number_format($item->orderItem->price ?? 0, 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <table width="100%" style="margin-bottom: 25px;">
                <tr>
                    <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                        <span style="color: #64748b; font-size: 14px;">Reason for Return</span>
                    </td>
                    <td align="right" style="padding: 12px 0; border-bottom: 1px solid #f1f5f9;">
                        <span
                            style="background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 600;">{{ $returnOrder->reason }}</span>
                    </td>
                </tr>
            </table>

            <div style="border-left: 4px solid #cbd5e1; padding-left: 15px; margin: 20px 0;">
                <p style="margin: 0 0 5px; color: #94a3b8; font-size: 11px; text-transform: uppercase;">Customer Note
                </p>
                <p style="margin: 0; color: #475569; font-size: 14px; line-height: 1.6; font-style: italic;">
                    "{{ $returnOrder->note ?? 'No additional comments provided.' }}"
                </p>
            </div>

            <div style="text-align: center; margin-top: 35px;">
                <a href="{{ url('/admin/returns/' . $returnOrder->id) }}"
                    style="background-color: #2563eb; color: #ffffff; padding: 14px 40px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block;">
                    Review in Dashboard
                </a>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="margin: 0; color: #94a3b8; font-size: 11px;">VENDOR MANAGEMENT SYSTEM • {{ date('Y') }}</p>
        </div>
    </div>
</div>