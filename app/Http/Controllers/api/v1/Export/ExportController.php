<?php

namespace App\Http\Controllers\api\v1\Export;

use App\Exports\OrderHistoryExport;
use App\Exports\ProductExport;
use App\Exports\ProductsReportExport;
use App\Exports\ReturnHistoryExport;
use App\Exports\SalesReportExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
class ExportController extends Controller
{
    public function exportOrderHistory()
    {
        return Excel::download(new OrderHistoryExport(), 'order_history.xlsx');
    }
    public function exportProducts()
    {
        return Excel::download(new ProductExport(), 'products.xlsx');
    }
    public function exportSalesReport(Request $request)
    {
        return Excel::download(
            new SalesReportExport($request->start_date, $request->end_date),
            'sales_report.xlsx'
        );
    }
    public function exportProductsReport(Request $request)
    {
        return Excel::download(
            new ProductsReportExport($request->start_date, $request->end_date),
            'products_report.xlsx'
        );
    }
    public function exportProductsPdf(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $query = OrderItem::with(['productVariant.product.category', 'order'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['completed', 'delivered']);
            });

        if ($startDate && $endDate) {
            $query->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ]);
            });
        }

        $products = $query
            ->select('product_variant_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(price * quantity) as total_revenue')
            ->groupBy('product_variant_id')
            ->get()
            ->map(function ($item) {
                $variant = $item->productVariant;
                $product = optional($variant)->product;

                return [
                    'variant_id' => $item->product_variant_id,
                    'product_name' => optional($product)->productName,
                    'category' => optional($product->category)->name,
                    'size' => optional($variant)->size,
                    'color' => optional($variant)->color,
                    'total_quantity' => $item->total_quantity,
                    'total_revenue' => $item->total_revenue,
                ];
            });

        $pdf = Pdf::loadView('reports.products-report', [
            'products' => $products,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $fileName = 'products_report_' .
            Carbon::parse($startDate)->format('d-m-Y') .
            '_to_' .
            Carbon::parse($endDate)->format('d-m-Y') .
            '.pdf';

        return $pdf->download($fileName);
    }
    public function exportSalesReportPdf(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $query = Order::with('payment');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        $statsQuery = clone $query;

        $totalOrders = $statsQuery->count();

        $totalEarnings = (clone $statsQuery)
            ->whereIn('status', ['completed', 'delivered'])
            ->sum('total_price');

        $totalDiscounts = (clone $statsQuery)->sum('discount_amount');
        $shippingCharges = (clone $statsQuery)->sum('shipping_fee');

        $orders = $query->latest()->get();

        $stats = [
            'total_orders' => $totalOrders,
            'total_earnings' => number_format($totalEarnings, 2),
            'total_discounts' => number_format($totalDiscounts, 2),
            'shipping_charges' => number_format($shippingCharges, 2),
        ];

        $pdf = Pdf::loadView('reports.sales-report', [
            'orders' => $orders,
            'stats' => $stats,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);

        $fileName = 'sales_report_' .
            Carbon::parse($startDate)->format('d-m-Y') .
            '_to_' .
            Carbon::parse($endDate)->format('d-m-Y') .
            '.pdf';

        return $pdf->download($fileName);
    }
    public function orderHistoryPdf()
    {
        $orders = Order::with('user', 'items', 'payment')
            ->latest()
            ->get();

        $pdf = Pdf::loadView('reports.order-history', [
            'orders' => $orders
        ]);

        return $pdf->download('order_history_' . now()->format('d-m-Y') . '.pdf');
    }
    public function productHistoryPdf()
    {
        $products = Product::with(['category', 'brand', 'variants'])
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->productName,
                    'category' => optional($product->category)->name ?? 'N/A',
                    'brand' => optional($product->brand)->name ?? 'N/A',
                    'price' => $product->price,
                    'stock' => $product->variants->sum('stock') ?? 0,
                    'status' => $product->status ?? 'active',
                    'variants_count' => $product->variants->count(),
                    'created_at' => $product->created_at->format('Y-m-d'),
                ];
            });

        $pdf = Pdf::loadView('reports.products', [
            'products' => $products
        ]);

        return $pdf->download('products_report_' . now()->format('d-m-Y') . '.pdf');

    }
    public function exportReturnHistoryExcel()
    {
        return Excel::download(
            new ReturnHistoryExport(),
            'return_history_' . now()->format('d-m-Y') . '.xlsx'
        );
    }
    public function exportReturnHistoryPdf()
    {
        $returns = ReturnOrder::with(['user', 'items', 'order.payment'])
            ->latest()
            ->get();

        $pdf = Pdf::loadView('reports.return-history', [
            'returns' => $returns
        ]);

        return $pdf->download('return_history_' . now()->format('d-m-Y') . '.pdf');
    }
}
