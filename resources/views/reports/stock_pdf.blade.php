<!doctype html>
<html>

<head>
    <title>Stock Report</title>
    <style>
        body {
            font-family: "Helvetica", "Arial", sans-serif;
            font-size: 12px;
            color: #5c6770;
            margin: 20px;
        }

        h3 {
            color: #2c3e50;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .stats {
            margin-bottom: 20px;
        }

        .stats p {
            margin: 3px 0;
        }

        thead {
            background: #2c3e50;
            color: white;
        }

        .report-table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table thead th {
            padding: 10px;
            text-align: left;
            font-size: 10px;
        }

        .report-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .product-name {
            font-weight: bold;
        }

        .variant-indent {
            padding-left: 40px;
            font-style: italic;
            color: #777;
        }

        .sku-badge {
            background: #eee;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
        }

        .status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
        }

        .IN_STOCK {
            background: #e0f9f1;
            color: #27ae60;
        }

        .LOW {
            background: #fff4e5;
            color: #f39c12;
        }

        .OUT_OF_STOCK {
            background: #ffebee;
            color: #e74c3c;
        }

        .stock-count {
            font-weight: bold;
        }
    </style>
</head>

<body>

    <h3>Stock Report</h3>

    <!-- Summary -->
    <div class="stats">
        <p><strong>Total Products:</strong> {{ $summary['total_products'] }}</p>
        <p><strong>Out of Stock:</strong> {{ $summary['out_of_stock'] }}</p>
        <p><strong>Low Stock:</strong> {{ $summary['low_stock'] }}</p>
        <p><strong>Total Units:</strong> {{ $summary['total_units'] }}</p>
        <p><strong>Total Categories:</strong> {{ $summary['total_categories'] }}</p>
    </div>

    <!-- Table -->
    <table class="report-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU / ID</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Status</th>
            </tr>
        </thead>

        <tbody>
            @foreach($data as $item)

                <!-- Product Row -->
                <tr>
                    <td class="product-name">{{ $item['name'] }}</td>
                    <td><span class="sku-badge">PROD-{{ $item['id'] }}</span></td>
                    <td>{{ $item['category'] }}</td>
                    <td class="stock-count">{{ $item['total_stock'] }}</td>
                    <td>
                        <span class="status {{ $item['status'] }}">
                            {{ str_replace('_', ' ', $item['status']) }}
                        </span>
                    </td>
                </tr>

                <!-- Variants -->
                @foreach($item['variants'] as $variant)
                    <tr>
                        <td class="variant-indent">
                            Color: {{ $variant['color'] }},
                            Size: {{ $variant['size'] }}
                        </td>
                        <td><span class="sku-badge">SKU-{{ $variant['id'] }}</span></td>
                        <td>---</td>
                        <td class="stock-count">{{ $variant['stock'] }}</td>
                        <td>
                            <span class="status {{ $variant['status'] }}">
                                {{ str_replace('_', ' ', $variant['status']) }}
                            </span>
                        </td>
                    </tr>
                @endforeach

            @endforeach
        </tbody>
    </table>

</body>
</html>