<?php

namespace App\Http\Controllers\api\v1\Export;

use App\Exports\AdminsExport;
use App\Exports\OrderHistoryExport;
use App\Exports\ProductExport;
use App\Exports\ProductsReportExport;
use App\Exports\ProductVariantsExport;
use App\Exports\ReturnHistoryExport;
use App\Exports\SalesReportExport;
use App\Exports\StockExport;
use App\Exports\UsersExport;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnOrder;
use App\Models\User;
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
    public function exportUsersExcel()
    {
        return Excel::download(new UsersExport(), 'users.xlsx');
    }
    public function exportUsersPDF(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $users = User::where('role', 'customer')->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })->get();

        $stats = [
            'total_users' => $users->count(),
            'active_users' => $users->where('status', 'active')->count(),
            'inactive_users' => $users->where('status', 'inactive')->count(),
            'new_users' => $users->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];

        $pdf = Pdf::loadView('reports.users', compact('users', 'stats', 'startDate', 'endDate'));

        return $pdf->download('users-report.pdf');
    }
    public function exportAdminsExcel()
    {
        return Excel::download(new AdminsExport(), 'admins.xlsx');
    }
    public function exportAdminsPDF(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $users = User::where('role', 'admin')->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })->get();

        $stats = [
            'total_admins' => $users->count(),
            'active_admins' => $users->where('status', 'active')->count(),
            'inactive_admins' => $users->where('status', 'inactive')->count(),
            'new_admins' => $users->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];

        $pdf = Pdf::loadView('reports.admins', compact('users', 'stats', 'startDate', 'endDate'));

        return $pdf->download('admins-report.pdf');
    }
    // Stocks
    public function exportStock()
    {
        return Excel::download(new StockExport(), 'stock-report.xlsx');
    }
    public function exportStockPDF()
    {
        $products = Product::with('variants')->get();

        $data = $products->map(function ($p) {

            $totalStock = $p->variants->sum('stock');
            $lowVariants = $p->variants->where('stock', '<=', 5);

            return [
                'id' => $p->id,
                'name' => $p->productName,
                'price' => $p->price,
                'total_stock' => $totalStock,
                'low_stock_count' => $lowVariants->count(),

                'status' => match (true) {
                    $totalStock == 0 => 'OUT_OF_STOCK',
                    $totalStock <= 5 => 'CRITICAL_LOW',
                    $totalStock <= 10 => 'LOW',
                    default => 'IN_STOCK'
                },

                'alerts' => $lowVariants->map(function ($v) {
                    return "{$v->size}/{$v->color} ({$v->stock})";
                })->implode(', ')
            ];
        });

        $summary = [
            'total_products' => $products->count(),
            'out_of_stock' => $products->filter(fn($p) => $p->variants->sum('stock') == 0)->count(),
            'low_stock' => $products->filter(fn($p) => $p->variants->sum('stock') <= 10)->count(),
            'total_units' => $products->sum(fn($p) => $p->variants->sum('stock')),
        ];

        $pdf = Pdf::loadView('reports.stock_pdf', compact('data', 'summary'));

        return $pdf->download('stock-report.pdf');
    }
    // Variants
    public function exportVariantsExcel()
    {
        return Excel::download(new ProductVariantsExport(), 'product-variants.xlsx');
    }
    public function exportVariantPDF()
    {
        $variants = ProductVariant::with('product')->get();

        $data = $variants->map(function ($v) {

            return [
                'id' => $v->id,
                'product' => $v->product->productName ?? '-',
                'size' => $v->size,
                'color' => $v->color,
                'price' => $v->price ?? $v->product->price,
                'stock' => $v->stock,

                'status' => match (true) {
                    $v->stock == 0 => 'OUT',
                    $v->stock <= 2 => 'CRITICAL',
                    $v->stock <= 5 => 'LOW',
                    default => 'OK'
                }
            ];
        });

        $summary = [
            'total_variants' => $variants->count(),
            'out_of_stock' => $variants->where('stock', 0)->count(),
            'low_stock' => $variants->where('stock', '<=', 5)->count(),
            'total_units' => $variants->sum('stock'),
        ];

        $pdf = Pdf::loadView('reports.variant_pdf', compact('data', 'summary'));

        return $pdf->download('product-variants-report.pdf');
    }

}
