<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Notification;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'today');
        switch ($filter) {
            case 'today':
                $start = Carbon::today();
                $end = Carbon::today()->endOfDay();
                break;

            case 'week':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                break;

            case 'month':
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                break;

            case 'year':
                $start = Carbon::now()->startOfYear();
                $end = Carbon::now()->endOfYear();
                break;

            default:
                $start = Carbon::today();
                $end = Carbon::today()->endOfDay();
        }
        $ordersQuery = Order::whereBetween('created_at', [$start, $end]);
        $totalOrders = (clone $ordersQuery)->count();

        $totalRevenue = DB::table('payments')
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
        $statusCounts = [
            'Pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
            'Confirmed' => (clone $ordersQuery)->where('status', 'confirmed')->count(),
            'Shipping' => (clone $ordersQuery)->where('status', 'shipping')->count(),
            'Delivered' => (clone $ordersQuery)->where('status', 'delivered')->count(),
            'Cancelled' => (clone $ordersQuery)->where('status', 'cancelled')->count(),
        ];
        $chartData = [
            'total' => array_sum($statusCounts),
            'labels' => array_keys($statusCounts),
            'series' => array_values($statusCounts),
        ];
        $revenueTrend = DB::table('payments')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as revenue')
            )

            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
        $formattedTrend = $revenueTrend->map(function ($item) {
            return [
                'date' => Carbon::parse($item->date)->format('M d'), // Apr 22
                'revenue' => (float) $item->revenue
            ];
        });
        return response()->json([
            'message' => 'Welcome to the Dashboard API',
            'filter' => $filter,
            'date_range' => [
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ],
            'data' => [
                // 'total_orders' => Order::count(),
                'total_revenue' => $totalRevenue,
                'active_orders' => (clone $ordersQuery)->where('status', '!=', 'completed')->count(),
                'total_customers' => User::count(),
                'total_products' => Product::count(),
                'total_brands' => Brand::count(),
                'order_statistics' => [
                    'total_orders' => $totalOrders,
                    'pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
                    'confirmed' => (clone $ordersQuery)->where('status', 'confirmed')->count(),
                    'completed' => (clone $ordersQuery)->where('status', 'completed')->count(),
                    'shipped' => (clone $ordersQuery)->where('status', 'shipping')->count(),
                    'delivered' => (clone $ordersQuery)->where('status', 'delivered')->count(),
                    'cancelled' => (clone $ordersQuery)->where('status', 'cancelled')->count(),
                    'returned' => ReturnOrder::where('status', 'completed')->count(),
                ],
                'revenue_trend' => $formattedTrend,
                'top_customers' => User::select('id', 'first_name', 'last_name', 'email')
                    ->withCount('orders')
                    ->orderByDesc('orders_count')
                    ->take(5)
                    ->get(),
                'top_sold_products' => DB::table('order_items')
                    ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
                    ->join('products', 'product_variants.product_id', '=', 'products.id')
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->join('payments', 'orders.id', '=', 'payments.order_id')
                    ->where('payments.status', 'paid')
                    ->whereBetween('orders.created_at', [$start, $end])
                    ->select(
                        'products.id',
                        'products.productName',
                        DB::raw('SUM(order_items.quantity) as total_sold')
                    )
                    ->groupBy('products.id', 'products.productName')
                    ->orderByDesc('total_sold')
                    ->take(5)
                    ->get(),
                'order_carts' => $chartData,
                'recent_orders' => Order::latest()->take(5)->get(),
            ]

        ]);

    }
    public function orderStat()
    {
        return response()->json([
            'message' => 'Order Status Distribution',
            'data' => [
                'total_orders' => Order::count(),
                'pending' => Order::where('status', 'pending')->count(),
                'confirmed' => Order::where('status', 'confirmed')->count(),
                'completed' => Order::where('status', 'completed')->count(),
                'shipped' => Order::where('status', 'shipping')->count(),
                'delivered' => Order::where('status', 'delivered')->count(),
                'cancelled' => Order::where('status', 'cancelled')->count(),
                'returned' => ReturnOrder::where('status', 'completed')->count(),
            ]
        ]);
    }
    public function stats()
    {
        $orders = Order::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->take(7)
            ->get();

        return response()->json([
            'message' => 'Dashboard Stats',
            'data' => [
                'orders_by_date' => $orders
            ]
        ]);
    }
    // most sold products
    public function mostSoldProducts()
    {
        $topProducts = DB::table('order_items')
            ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('payments', 'orders.id', '=', 'payments.order_id')
            ->where('orders.status', 'completed')
            ->where('payments.status', 'paid')

            ->select(
                'products.id',
                'products.productName',
                DB::raw('SUM(order_items.quantity) as total_sold')
            )
            ->groupBy('products.id', 'products.productName')
            ->orderByDesc('total_sold')
            ->take(5)
            ->get();

        return response()->json([
            'message' => 'Top products retrieved successfully',
            'data' => $topProducts
        ]);
    }
    // view all stocks
    public function stock()
    {
        $products = Product::with('variants')->get();

        $data = $products->map(function ($p) {

            $totalStock = $p->variants->sum('stock');
            $lowVariants = $p->variants->where('stock', '<=', 5);

            return [
                'id' => $p->id,
                'name' => $p->productName,
                'price' => (float) $p->price,

                // 📦 stock summary
                'stock' => [
                    'total' => $totalStock,
                    'available' => $totalStock,
                    'low_stock_count' => $lowVariants->count(),
                ],

                // 🚨 status (professional naming)
                'inventory_status' => match (true) {
                    $totalStock == 0 => 'OUT_OF_STOCK',
                    $totalStock <= 5 => 'CRITICAL_LOW',
                    $totalStock <= 10 => 'LOW',
                    default => 'IN_STOCK'
                },

                // ⚠️ only important variants
                'alerts' => $lowVariants->values()->map(function ($v) {
                    return [
                        'variant_id' => $v->id,
                        'size' => $v->size,
                        'color' => $v->color,
                        'stock' => $v->stock,
                        'severity' => $v->stock == 0 ? 'OUT' :
                            ($v->stock <= 2 ? 'CRITICAL' : 'LOW'),
                    ];
                })->values(),

                'has_alert' => $lowVariants->isNotEmpty(),
            ];
        });

        return response()->json([
            'message' => 'Stock Dashboard',
            'summary' => [
                'total_products' => $products->count(),
                'critical_low' => $products->filter(fn($p) => $p->variants->sum('stock') <= 5)->count(),
                'out_of_stock' => $products->filter(fn($p) => $p->variants->sum('stock') == 0)->count(),
                'total_stock_units' => $products->sum(fn($p) => $p->variants->sum('stock')),
            ],
            'data' => $data
        ]);
    }
    // stock alert 
    public function stockAlert()
    {
        $products = Product::whereHas('variants', function ($query) {
            $query->where('stock', '<=', 5);
        })->get();
        $admins = User::where('role', 'admin')->get();

        foreach ($products as $product) {
            Notification::send($admins, new LowStockNotification($product));
        }
        return response()->json([
            'message' => 'Low Stock Alert',
            'data' => $products
        ]);
    }



}
