<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { background-color: #f4f4f4; margin: 0; padding: 0; font-family: 'Inter', Helvetica, Arial, sans-serif; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f4f4f4; padding-bottom: 40px; padding-top: 40px; }
        .main-card { width: 100%; max-width: 450px; background-color: #ffffff; border-radius: 12px; border: 1px solid #eeeeee; margin: 0 auto; overflow: hidden; }
        .status-banner { background-color: #fff1f0; color: #d93025; padding: 12px 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; border-bottom: 1px solid #ffa39e; }
        .product-header { padding: 24px; border-bottom: 1px solid #eeeeee; }
        .product-name { font-size: 18px; font-weight: 700; color: #1a1a1a; margin: 0; }
        .sku-badge { font-size: 12px; color: #666666; background: #f8f9fa; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 5px; }
        .details-table { width: 100%; padding: 20px 24px; border-spacing: 0; }
        .detail-label { font-size: 11px; color: #999999; text-transform: uppercase; font-weight: 700; padding-bottom: 4px; }
        .detail-value { font-size: 14px; color: #1a1a1a; font-weight: 500; padding-bottom: 20px; }
        .danger-text { color: #d93025; font-weight: 700; }
        .footer { padding: 20px; background-color: #f8f9fa; text-align: center; }
        .btn { background-color: #1a1a1a; color: #ffffff !important; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="main-card">
            <div class="status-banner">
                OFFER EXPIRED &bull; {{ $discount->end_date }}
            </div>
            
            <div class="product-header">
                <p class="product-name">{{ $discount->product->name ?? 'Clothing Item' }}</p>
                <span class="sku-badge">SKU: {{ $discount->product->sku ?? 'N/A' }}</span>
            </div>

            <table class="details-table">
                <tr>
                    <td width="50%" class="detail-label">Discount Name</td>
                    <td width="50%" class="detail-label">Value Lost</td>
                </tr>
                <tr>
                    <td class="detail-value">{{ $discount->name }}</td>
                    <td class="detail-value danger-text">-{{ $discount->amount }}</td>
                </tr>
                <tr>
                    <td class="detail-label">Category</td>
                    <td class="detail-label">Status</td>
                </tr>
                <tr>
                    <td class="detail-value">{{ $discount->product->category ?? 'Retail' }}</td>
                    <td class="detail-value">Inactive</td>
                </tr>
            </table>

            <div class="footer">
                <a href="{{ url('/admin/discounts') }}" class="btn">Manage Inventory</a>
            </div>
        </div>
    </div>
</body>
</html>