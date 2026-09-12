<?php

namespace App\Http\Controllers\api\v1\Admin;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnOrder;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Notification;
use Illuminate\Http\JsonResponse;
class DashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        // 1. Resolve Date Ranges (Current & Previous Periods)
        [$start, $end] = $this->resolveDateRange($request);
        [$previousStart, $previousEnd] = $this->resolvePreviousDateRange($start, $end);

        // 2. Base Queries
        $ordersQuery = Order::whereBetween('created_at', [$start, $end]);
        $returnQuery = ReturnOrder::whereBetween('created_at', [$start, $end]);

        // 3. Core Metrics Calculations
        $totalOrders = (clone $ordersQuery)->count();
        $previousOrders = Order::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $ordersPercentageChange = $this->calculatePercentageChange($previousOrders, $totalOrders);

        $totalRevenue = $this->getRevenueBetween($start, $end);
        $previousRevenue = $this->getRevenueBetween($previousStart, $previousEnd);
        $revenuePercentageChange = $this->calculatePercentageChange($previousRevenue, $totalRevenue);

        // 4. Customer Metrics
        $totalCustomers = User::count();
        $currentNewCustomers = User::whereBetween('created_at', [$start, $end])->count();
        $previousNewCustomers = User::whereBetween('created_at', [$previousStart, $previousEnd])->count();
        $customersPercentageChange = $this->calculatePercentageChange($previousNewCustomers, $currentNewCustomers);

        // 5. Additional Dashboard Data
        $statusCounts = $this->getOrderStatusCounts($ordersQuery);
        $chartData = [
            'total' => array_sum($statusCounts),
            'labels' => array_keys($statusCounts),
            'series' => array_values($statusCounts),
        ];

        $formattedTrend = $this->getRevenueTrend($start, $end);
        $hours = $this->getOrderDistributionByHour($start, $end);
        $lowStockProducts = ProductVariant::where('stock', '<=', 5)->get();
        $totalBrands = $this->getActiveBrandsCount($start, $end);
        $profitMargin = $totalRevenue > 0 ? 100 : 0;

        // 6. Return Structured JSON Response
        return response()->json([
            'message' => 'Welcome to the Dashboard API',
            'filter' => $request->get('filter', 'today'),
            'date_range' => [
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ],
            'data' => [
                'total_revenue' => $totalRevenue,
                'revenue_percentage_change' => $revenuePercentageChange,
                'profit_margin' => $profitMargin . '% Margin',
                'active_orders' => (clone $ordersQuery)->where('status', '!=', 'completed')->count(),
                'orders_percentage_change' => $ordersPercentageChange,
                'total_customers' => $totalCustomers,
                'customers_percentage_change' => $customersPercentageChange,
                'new_customers_count' => '+' . $currentNewCustomers . ' new this period',
                'total_products' => Product::count(),
                'total_brands_label' => $totalBrands . ' Brands',
                'total_brands' => $totalBrands,
                'order_statistics' => [
                    'total_orders' => $totalOrders,
                    'pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
                    'confirmed' => (clone $ordersQuery)->where('status', 'confirmed')->count(),
                    'completed' => (clone $ordersQuery)->where('status', 'completed')->count(),
                    'shipped' => (clone $ordersQuery)->where('status', 'shipping')->count(),
                    'delivered' => (clone $ordersQuery)->where('status', 'delivered')->count(),
                    'cancelled' => (clone $ordersQuery)->where('status', 'cancelled')->count(),
                    'returned' => (clone $returnQuery)->where('status', 'completed')->count(),
                ],
                'revenue_trend' => $formattedTrend,
                'top_customers' => User::select('id', 'first_name', 'last_name', 'email', 'image')
                    ->withCount('orders')
                    ->whereHas('orders')
                    ->orderByDesc('orders_count')
                    ->take(5)
                    ->get(),
                'customer_stats' => [
                    'order_distribution_by_hour' => $hours,
                ],
                'top_sold_products' => $this->getTopSoldProducts($start, $end),
                'order_carts' => $chartData,
                'inventory_alerts' => $lowStockProducts->map(function ($variant) {
                    return [
                        'variant_id' => $variant->id,
                        'product_id' => $variant->product_id,
                        'product_name' => $variant->product->productName,
                        'size' => $variant->size,
                        'color' => $variant->color,
                        'stock' => $variant->stock,
                    ];
                }),
                'recent_orders' => Order::latest()->take(5)->get()->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'first_name' => $order->user->first_name,
                        'last_name' => $order->user->last_name,
                        'status' => $order->status,
                        'total_price' => $order->total_price,
                        'ordered_date' => $order->created_at->format('d-m-Y'),
                        'time' => $order->created_at->format(' h:i:s A'),
                    ];
                })->values(),
            ],
        ]);
    }
    private function calculatePercentageChange($previous, $current): string
    {
        if ($previous == 0) {
            return $current > 0 ? '+100%' : '0%';
        }

        $change = (($current - $previous) / $previous) * 100;
        $prefix = $change >= 0 ? '+' : '';

        return $prefix . number_format($change, 1) . '%';
    }
    // Resolve start and end Carbon instances based on request filters.
    private function resolveDateRange(Request $request): array
    {
        $filter = $request->get('filter', 'today');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ];
        }

        if ($request->filled('date')) {
            return [
                Carbon::parse($request->date)->startOfDay(),
                Carbon::parse($request->date)->endOfDay(),
            ];
        }

        return match ($filter) {
            'yesterday' => [
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
            ],
            'week' => [
                Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY),
                Carbon::now()->subWeek()->endOfWeek(Carbon::SUNDAY),
            ],
            'month' => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ],
            'year' => [
                Carbon::now()->startOfYear(),
                Carbon::now()->endOfYear(),
            ],
            default => [
                Carbon::today(),
                Carbon::today()->endOfDay(),
            ],
        };
    }
    // Calculate previous period date range for growth comparisons.
    private function resolvePreviousDateRange(Carbon $start, Carbon $end): array
    {
        $durationInDays = $start->diffInDays($end) + 1;

        return [
            (clone $start)->subDays($durationInDays),
            (clone $end)->subDays($durationInDays),
        ];
    }
    // Calculate percentage change between two values.
    private function getRevenueBetween(Carbon $start, Carbon $end): float
    {
        return (float) DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('payments.status', 'paid')
            ->whereBetween('orders.created_at', [$start, $end])
            ->sum('payments.amount');
    }
    // Fetch daily revenue trend data.
    private function getOrderStatusCounts($ordersQuery): array
    {
        return [
            'Pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
            'Confirmed' => (clone $ordersQuery)->where('status', 'confirmed')->count(),
            'Shipping' => (clone $ordersQuery)->where('status', 'shipping')->count(),
            'Delivered' => (clone $ordersQuery)->where('status', 'delivered')->count(),
            'Completed' => (clone $ordersQuery)->where('status', 'completed')->count(),
            'Cancelled' => (clone $ordersQuery)->where('status', 'cancelled')->count(),
        ];
    }
    // Fetch daily revenue trend data.
    private function getRevenueTrend(Carbon $start, Carbon $end)
    {
        return DB::table('payments')
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(amount) as revenue')
            )
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => Carbon::parse($item->date)->format('M d'),
                    'revenue' => (float) $item->revenue,
                ];
            });
    }
    // Get customer order distribution by hour.
    private function getOrderDistributionByHour(Carbon $start, Carbon $end)
    {
        $orderDistribution = Order::whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('EXTRACT(HOUR FROM created_at) as hour'),
                DB::raw('COUNT(DISTINCT user_id) as count')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return collect(range(0, 23))->map(function ($hour) use ($orderDistribution) {
            $found = $orderDistribution->firstWhere('hour', $hour);

            return [
                'hour' => Carbon::createFromTime($hour)->format('g A'),
                'customer_count' => $found ? $found->count : 0,
            ];
        });
    }
    // Get top 5 sold products.
    private function getTopSoldProducts(Carbon $start, Carbon $end)
    {
        return DB::table('order_items')
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
            ->get();
    }
    // Add this helper function at the bottom of your controller
private function getActiveBrandsCount(Carbon $start, Carbon $end): int
{
    return DB::table('order_items')
        ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
        ->join('products', 'product_variants.product_id', '=', 'products.id')
        ->join('orders', 'order_items.order_id', '=', 'orders.id')
        ->join('payments', 'orders.id', '=', 'payments.order_id')
        ->where('payments.status', 'paid')
        ->whereBetween('orders.created_at', [$start, $end])
        ->whereNotNull('products.brand_id')
        ->distinct('products.brand_id')
        ->count('products.brand_id');
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
    //     public function dashboard(Request $request)
//     {
//         $filter = $request->get('filter', 'today');

    //         if ($request->filled('start_date') && $request->filled('end_date')) {
//             // Custom date range
//             $start = Carbon::parse($request->start_date)->startOfDay();
//             $end = Carbon::parse($request->end_date)->endOfDay();

    //         } elseif ($request->filled('date')) {
//             // Single date
//             $start = Carbon::parse($request->date)->startOfDay();
//             $end = Carbon::parse($request->date)->endOfDay();

    //         } else {
//             // Default filters
//             switch ($filter) {
//                 case 'today':
//                     $start = Carbon::today();
//                     $end = Carbon::today()->endOfDay();
//                     break;

    //                 case 'yesterday':
//                     $start = Carbon::yesterday()->startOfDay();
//                     $end = Carbon::yesterday()->endOfDay();
//                     break;

    //                 case 'week':
//                     $start = Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY);
//                     $end = Carbon::now()->subWeek()->endOfWeek(Carbon::SUNDAY);
//                     break;

    //                 case 'month':
//                     $start = Carbon::now()->startOfMonth();
//                     $end = Carbon::now()->endOfMonth();
//                     break;

    //                 case 'year':
//                     $start = Carbon::now()->startOfYear();
//                     $end = Carbon::now()->endOfYear();
//                     break;

    //                 default:
//                     $start = Carbon::today();
//                     $end = Carbon::today()->endOfDay();
//             }
//         }

    //         $ordersQuery = Order::whereBetween('created_at', [$start, $end]);
//         $returnQuery = ReturnOrder::whereBetween('created_at', [$start, $end]);
//         $totalOrders = (clone $ordersQuery)->count();
//         $durationInDays = $start->diffInDays($end) + 1;
//         $previousStart = (clone $start)->subDays($durationInDays);
//         $previousEnd = (clone $end)->subDays($durationInDays);
//         $totalRevenue = DB::table('payments')
//             ->join('orders', 'payments.order_id', '=', 'orders.id')
//             ->where('payments.status', 'paid')
//             ->whereBetween('orders.created_at', [$start, $end])
//             ->sum('payments.amount');
//         $statusCounts = [
//             'Pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
//             'Confirmed' => (clone $ordersQuery)->where('status', 'confirmed')->count(),
//             'Shipping' => (clone $ordersQuery)->where('status', 'shipping')->count(),
//             'Delivered' => (clone $ordersQuery)->where('status', 'delivered')->count(),
//             'Completed' => (clone $ordersQuery)->where('status', 'completed')->count(),
//             'Cancelled' => (clone $ordersQuery)->where('status', 'cancelled')->count(),
//         ];
//         $chartData = [
//             'total' => array_sum($statusCounts),
//             'labels' => array_keys($statusCounts),
//             'series' => array_values($statusCounts),
//         ];
//         $revenueTrend = DB::table('payments')
//             ->select(
//                 DB::raw('DATE(created_at) as date'),
//                 DB::raw('SUM(amount) as revenue')
//             )

    //             ->where('status', 'paid')
//             ->whereBetween('created_at', [$start, $end])
//             ->groupBy(DB::raw('DATE(created_at)'))
//             ->orderBy('date')
//             ->get();
//         $formattedTrend = $revenueTrend->map(function ($item) {
//             return [
//                 'date' => Carbon::parse($item->date)->format('M d'), // Apr 22
//                 'revenue' => (float) $item->revenue
//             ];
//         });
//         // --- 2. CURRENT VS PREVIOUS METRICS ---

    //         // Total Revenue (Current vs Previous)
//         $totalRevenue = DB::table('payments')
//             ->join('orders', 'payments.order_id', '=', 'orders.id')
//             ->where('payments.status', 'paid')
//             ->whereBetween('orders.created_at', [$start, $end])
//             ->sum('payments.amount');
//         $previousRevenue = DB::table('payments')
//             ->join('orders', 'payments.order_id', '=', 'orders.id')
//             ->where('payments.status', 'paid')
//             ->whereBetween('orders.created_at', [$previousStart, $previousEnd])
//             ->sum('payments.amount');
//         // revenue Percentage Change
//         $revenuePercentageChange = $this->calculatePercentageChange($previousRevenue, $totalRevenue);
//         // Query order distribution by hour
//         $orderDistribution = Order::whereBetween('created_at', [$start, $end])
//             ->select(
//                 DB::raw('EXTRACT(HOUR FROM created_at) as hour'),
//                 // DB::raw('count(*) as count')
//                 DB::raw("COUNT(DISTINCT user_id) as count")
//             )
//             ->groupBy('hour')
//             ->orderBy('hour')
//             ->get();
//         // Total Orders (Current vs Previous)
//         $ordersQuery = Order::whereBetween('created_at', [$start, $end]);
//         $totalOrders = (clone $ordersQuery)->count();

    //         $previousOrders = Order::whereBetween('created_at', [$previousStart, $previousEnd])->count();
//         $ordersPercentageChange = $this->calculatePercentageChange($previousOrders, $totalOrders);
//         // Map it to ensure it looks clean for your frontend chart
//         $hours = collect(range(0, 23))->map(function ($hour) use ($orderDistribution) {
//             $found = $orderDistribution->firstWhere('hour', $hour);
//             return [
//                 'hour' => Carbon::createFromTime($hour)->format('g A'),
//                 'customer_count' => $found ? $found->count : 0
//             ];
//         });
//         // --- Total Customers (Current vs Previous) ---
// // Total customers right now
//         $totalCustomers = User::count();

    //         // New customers acquired during the selected period
//         $currentNewCustomers = User::whereBetween('created_at', [$start, $end])->count();

    //         // New customers acquired during the previous equivalent period
//         $previousNewCustomers = User::whereBetween('created_at', [$previousStart, $previousEnd])->count();

    //         // Calculate percentage change based on new acquisition rate
//         $customersPercentageChange = $this->calculatePercentageChange($previousNewCustomers, $currentNewCustomers);
//         // Stock alert data
//         $lowStockProducts = ProductVariant::where('stock', '<=', 5)->get();
//         return response()->json([
//             'message' => 'Welcome to the Dashboard API',
//             'filter' => $filter,
//             'date_range' => [
//                 'start' => $start->toDateTimeString(),
//                 'end' => $end->toDateTimeString(),
//             ],
//             'data' => [
//                 'total_revenue' => $totalRevenue,
//                 'revenue_percentage_change' => $revenuePercentageChange,
//                 'profit_margin' => $profitMargin . '% Margin',
//                 'active_orders' => (clone $ordersQuery)->where('status', '!=', 'completed')->count(),
//                 'orders_percentage_change' => $ordersPercentageChange,
//                 'total_customers' => User::count(),
//                 'customers_percentage_change' => $customersPercentageChange,
//                 'new_customers_count' => '+' . $newCustomersCount . ' new this period',
//                 'total_products' => Product::count(),
//                 'total_brands_label' => $totalBrands . ' Brands',
//                 'total_brands' => Brand::count(),
//                 'order_statistics' => [
//                     'total_orders' => $totalOrders,
//                     'pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
//                     'confirmed' => (clone $ordersQuery)->where('status', 'confirmed')->count(),
//                     'completed' => (clone $ordersQuery)->where('status', 'completed')->count(),
//                     'shipped' => (clone $ordersQuery)->where('status', 'shipping')->count(),
//                     'delivered' => (clone $ordersQuery)->where('status', 'delivered')->count(),
//                     'cancelled' => (clone $ordersQuery)->where('status', 'cancelled')->count(),
//                     'returned' => (clone $returnQuery)->where('status', 'completed')->count(),
//                 ],
//                 'revenue_trend' => $formattedTrend,
//                 'top_customers' => User::select('id', 'first_name', 'last_name', 'email', 'image')
//                     ->withCount('orders')
//                     ->whereHas('orders')
//                     ->orderByDesc('orders_count')
//                     ->take(5)
//                     ->get(),
//                 'customer_stats' => [
//                     'order_distribution_by_hour' => $hours
//                 ],
//                 'top_sold_products' => DB::table('order_items')
//                     ->join('product_variants', 'order_items.product_variant_id', '=', 'product_variants.id')
//                     ->join('products', 'product_variants.product_id', '=', 'products.id')
//                     ->join('orders', 'order_items.order_id', '=', 'orders.id')
//                     ->join('payments', 'orders.id', '=', 'payments.order_id')
//                     ->where('payments.status', 'paid')
//                     ->whereBetween('orders.created_at', [$start, $end])
//                     ->select(
//                         'products.id',
//                         'products.productName',
//                         DB::raw('SUM(order_items.quantity) as total_sold')
//                     )
//                     ->groupBy('products.id', 'products.productName')
//                     ->orderByDesc('total_sold')
//                     ->take(5)
//                     ->get(),
//                 // 'order_distribution' => $formattedDistribution,
//                 'order_carts' => $chartData,
//                 'inventory_alerts' => $lowStockProducts->map(function ($variant) {
//                     return [
//                         'variant_id' => $variant->id,
//                         'product_id' => $variant->product_id,
//                         'product_name' => $variant->product->productName,
//                         'size' => $variant->size,
//                         'color' => $variant->color,
//                         'stock' => $variant->stock,
//                     ];
//                 }),
//                 'recent_orders' => Order::latest()->take(5)->get()->map(function ($order) {
//                     return [
//                         'id' => $order->id,
//                         'order_number' => $order->order_number,
//                         'first_name' => $order->user->first_name,
//                         'last_name' => $order->user->last_name,
//                         'status' => $order->status,
//                         'total_price' => $order->total_price,
//                         'ordered_date' => $order->created_at->format('d-m-Y'),
//                         'time' => $order->created_at->format(' h:i:s A')
//                     ];
//                 })->values(),
//             ]

    //         ]);

    //     }

}
