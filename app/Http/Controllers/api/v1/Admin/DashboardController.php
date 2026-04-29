<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
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
    public function dashboard(Request $request)
    {
        $filter = $request->get('filter', 'today');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            // Custom date range
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();

        } elseif ($request->filled('date')) {
            // Single date
            $start = Carbon::parse($request->date)->startOfDay();
            $end = Carbon::parse($request->date)->endOfDay();

        } else {
            // Default filters
            switch ($filter) {
                case 'today':
                    $start = Carbon::today();
                    $end = Carbon::today()->endOfDay();
                    break;

                case 'yesterday':
                    $start = Carbon::yesterday()->startOfDay();
                    $end = Carbon::yesterday()->endOfDay();
                    break;

                case 'week':
                    $start = Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY);
                    $end = Carbon::now()->subWeek()->endOfWeek(Carbon::SUNDAY);
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
        }

        $ordersQuery = Order::whereBetween('created_at', [$start, $end]);
        $returnQuery = ReturnOrder::whereBetween('created_at', [$start, $end]);
        $totalOrders = (clone $ordersQuery)->count();

        $totalRevenue = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('payments.status', 'paid')
            ->whereBetween('orders.created_at', [$start, $end])
            ->sum('payments.amount');
        $statusCounts = [
            'Pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
            'Confirmed' => (clone $ordersQuery)->where('status', 'confirmed')->count(),
            'Shipping' => (clone $ordersQuery)->where('status', 'shipping')->count(),
            'Delivered' => (clone $ordersQuery)->where('status', 'delivered')->count(),
            'Completed' => (clone $ordersQuery)->where('status', 'completed')->count(),
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
        // Query order distribution by hour
        $orderDistribution = Order::whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('EXTRACT(HOUR FROM created_at) as hour'),
                // DB::raw('count(*) as count')
                DB::raw("COUNT(DISTINCT user_id) as count")
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();
        // Map it to ensure it looks clean for your frontend chart
        $hours = collect(range(0, 23))->map(function ($hour) use ($orderDistribution) {
            $found = $orderDistribution->firstWhere('hour', $hour);
            return [
                'hour' => Carbon::createFromTime($hour)->format('g A'),
                'customer_count' => $found ? $found->count : 0
            ];
        });

        // Stock alert data
        $lowStockProducts = ProductVariant::where('stock', '<=', 5)->get();
        return response()->json([
            'message' => 'Welcome to the Dashboard API',
            'filter' => $filter,
            'date_range' => [
                'start' => $start->toDateTimeString(),
                'end' => $end->toDateTimeString(),
            ],
            'data' => [
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
                    'order_distribution_by_hour' => $hours
                ],
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
                // 'order_distribution' => $formattedDistribution,
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
                        'time' => $order->created_at->format(' h:i:s A')
                    ];
                })->values(),
            ]

        ]);

    }

    // Dashboard
    // public function dashboard(Request $request){
    //     $filter = $request->get('filter', 'today');

    //     // ----------------------------
    //     // 1. DATE RANGE
    //     // ----------------------------
    //     if ($request->filled('start_date') && $request->filled('end_date')) {
    //         $start = Carbon::parse($request->start_date)->startOfDay();
    //         $end = Carbon::parse($request->end_date)->endOfDay();

    //     } elseif ($request->filled('date')) {
    //         $start = Carbon::parse($request->date)->startOfDay();
    //         $end = Carbon::parse($request->date)->endOfDay();

    //     } else {
    //         switch ($filter) {
    //             case 'today':
    //                 $start = Carbon::today();
    //                 $end = Carbon::today()->endOfDay();
    //                 break;

    //             case 'yesterday':
    //                 $start = Carbon::yesterday()->startOfDay();
    //                 $end = Carbon::yesterday()->endOfDay();
    //                 break;

    //             case 'week':
    //                 $start = Carbon::now()->subWeek()->startOfWeek(Carbon::MONDAY);
    //                 $end = Carbon::now()->subWeek()->endOfWeek(Carbon::SUNDAY);
    //                 break;

    //             case 'month':
    //                 $start = Carbon::now()->startOfMonth();
    //                 $end = Carbon::now()->endOfMonth();
    //                 break;

    //             case 'year':
    //                 $start = Carbon::now()->startOfYear();
    //                 $end = Carbon::now()->endOfYear();
    //                 break;

    //             default:
    //                 $start = Carbon::today();
    //                 $end = Carbon::today()->endOfDay();
    //         }
    //     }

    //     // ----------------------------
    //     // 2. PREVIOUS PERIOD
    //     // ----------------------------
    //     $diff = $end->diffInSeconds($start);
    //     $prevStart = (clone $start)->subSeconds($diff + 1);
    //     $prevEnd = (clone $end)->subSeconds($diff + 1);

    //     // ----------------------------
    //     // 3. BASE QUERY
    //     // ----------------------------
    //     $ordersQuery = Order::whereBetween('created_at', [$start, $end]);

    //     // ----------------------------
    //     // 4. CURRENT DATA
    //     // ----------------------------
    //     $currentOrders = (clone $ordersQuery)->count();

    //     $currentRevenue = DB::table('payments')
    //         ->where('status', 'paid')
    //         ->whereBetween('created_at', [$start, $end])
    //         ->sum('amount');

    //     // ----------------------------
    //     // 5. PREVIOUS DATA
    //     // ----------------------------
    //     $previousOrders = Order::whereBetween('created_at', [$prevStart, $prevEnd])->count();

    //     $previousRevenue = DB::table('payments')
    //         ->where('status', 'paid')
    //         ->whereBetween('created_at', [$prevStart, $prevEnd])
    //         ->sum('amount');

    //     // ----------------------------
    //     // 6. CALCULATIONS
    //     // ----------------------------
    //     $currentAvg = $currentOrders > 0 ? $currentRevenue / $currentOrders : 0;
    //     $previousAvg = $previousOrders > 0 ? $previousRevenue / $previousOrders : 0;

    //     $revenueChange = $previousRevenue > 0
    //         ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100
    //         : 0;

    //     $ordersChange = $previousOrders > 0
    //         ? (($currentOrders - $previousOrders) / $previousOrders) * 100
    //         : 0;

    //     $avgChange = $previousAvg > 0
    //         ? (($currentAvg - $previousAvg) / $previousAvg) * 100
    //         : 0;

    //     $margin = $currentRevenue > 0 ? 100 : 0; // replace with real cost if available

    //     // ----------------------------
    //     // 7. EXTRA METRICS
    //     // ----------------------------
    //     $activeOrders = (clone $ordersQuery)
    //         ->whereNotIn('status', ['completed', 'cancelled'])
    //         ->count();

    //     $newCustomers = User::whereBetween('created_at', [$start, $end])->count();

    //     // ----------------------------
    //     // 8. STATUS COUNTS
    //     // ----------------------------
    //     $statusCounts = [
    //         'pending' => (clone $ordersQuery)->where('status', 'pending')->count(),
    //         'confirmed' => (clone $ordersQuery)->where('status', 'confirmed')->count(),
    //         'shipping' => (clone $ordersQuery)->where('status', 'shipping')->count(),
    //         'delivered' => (clone $ordersQuery)->where('status', 'delivered')->count(),
    //         'cancelled' => (clone $ordersQuery)->where('status', 'cancelled')->count(),
    //     ];

    //     // ----------------------------
    //     // 9. REVENUE TREND
    //     // ----------------------------
    //     $formattedTrend = DB::table('payments')
    //         ->select(
    //             DB::raw('DATE(created_at) as date'),
    //             DB::raw('SUM(amount) as revenue')
    //         )
    //         ->where('status', 'paid')
    //         ->whereBetween('created_at', [$start, $end])
    //         ->groupBy(DB::raw('DATE(created_at)'))
    //         ->orderBy('date')
    //         ->get()
    //         ->map(fn($item) => [
    //             'date' => Carbon::parse($item->date)->format('M d'),
    //             'revenue' => (float) $item->revenue
    //         ]);

    //     // ----------------------------
    //     // 10. ORDER DISTRIBUTION
    //     // ----------------------------
    //     $orderDistribution = Order::whereBetween('created_at', [$start, $end])
    //         ->select(
    //             DB::raw('EXTRACT(HOUR FROM created_at) as hour'),
    //             DB::raw("COUNT(DISTINCT user_id) as count")
    //         )
    //         ->groupBy('hour')
    //         ->get();

    //     $hours = collect(range(0, 23))->map(function ($hour) use ($orderDistribution) {
    //         $found = $orderDistribution->firstWhere('hour', $hour);

    //         return [
    //             'hour' => Carbon::createFromTime($hour)->format('g A'),
    //             'customer_count' => $found ? $found->count : 0
    //         ];
    //     });

    //     // ----------------------------
    //     // 11. LOW STOCK
    //     // ----------------------------
    //     $lowStockProducts = ProductVariant::where('stock', '<=', 5)->get();

    //     // ----------------------------
    //     // 12. RESPONSE
    //     // ----------------------------
    //     return response()->json([
    //         'message' => 'Welcome to the Dashboard API',
    //         'filter' => $filter,
    //         'date_range' => [
    //             'start' => $start->toDateTimeString(),
    //             'end' => $end->toDateTimeString(),
    //         ],
    //         'data' => [

    //             // DASHBOARD CARDS
    //             'total_revenue' => [
    //                 'value' => round($currentRevenue, 2),
    //                 'percent' => round($revenueChange, 1),
    //                 'margin' => round($margin, 2),
    //             ],

    //             'active_orders' => [
    //                 'value' => $activeOrders,
    //                 'percent' => round($ordersChange, 1),
    //                 'avg_order' => round($currentAvg, 2),
    //             ],

    //             'total_customers' => [
    //                 'value' => User::count(),
    //                 'new' => $newCustomers,
    //             ],

    //             'products' => [
    //                 'value' => Product::count(),
    //                 'brands' => Brand::count(),
    //             ],

    //             // OTHER DATA
    //             'order_statistics' => array_merge(['total_orders' => $currentOrders], $statusCounts),

    //             'revenue_trend' => $formattedTrend,

    //             'customer_stats' => [
    //                 'order_distribution_by_hour' => $hours
    //             ],

    //             'inventory_alerts' => $lowStockProducts->map(fn($v) => [
    //                 'variant_id' => $v->id,
    //                 'product_name' => $v->product->productName,
    //                 'stock' => $v->stock,
    //             ]),
    //         ]
    //     ]);
    // }
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
    // Sales report
    public function salesReport(Request $request)
    {
        //input date
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        // Base query
        $query = Order::with('payment');

        // Apply date 
        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        // Clone query 
        $statsQuery = clone $query;

        $totalOrders = $statsQuery->count();

        $totalEarnings = (clone $statsQuery)
            ->whereIn('status', ['completed', 'delivered'])
            ->sum('total_price');

        $totalDiscounts = (clone $statsQuery)->sum('discount_amount');
        $shippingCharges = (clone $statsQuery)->sum('shipping_fee');

        $orders = $query->latest()->get();

        return response()->json([
            'filter' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'stats' => [
                'total_orders' => $totalOrders,
                'total_earnings' => number_format($totalEarnings, 2),
                'total_discounts' => number_format($totalDiscounts, 2),
                'shipping_charges' => number_format($shippingCharges, 2),
            ],
            'orders' => $orders->map(function ($order) {
                return [
                    'order_id' => '#' . $order->order_number,
                    'date' => $order->created_at->format('M d, Y'),
                    'total' => $order->total_price,
                    'discount' => $order->discount_amount > 0 ? $order->discount_amount : 0,
                    'shipping' => $order->shipping_fee,
                    'payment' => [
                        'method' => optional($order->payment)->payment_method,
                        'status' => optional($order->payment)->status,
                    ],
                    'status' => $order->status,
                ];
            })
        ]);
    }
    // Products Report
    public function productsReport(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $query = OrderItem::with(['productVariant.product', 'order']);

        // Only completed/delivered orders
        $query->whereHas('order', function ($q) {
            $q->whereIn('status', ['completed', 'delivered']);
        });

        // Date filter
        if ($startDate && $endDate) {
            $query->whereHas('order', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ]);
            });
        }

        // Aggregate by product_variant_id
        $products = $query
            ->select('product_variant_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(price * quantity) as total_revenue')
            ->groupBy('product_variant_id')
            ->get();

        // Stats
        $totalRevenue = $products->sum('total_revenue');
        $totalQuantity = $products->sum('total_quantity');
        $totalProduct = $products->count();
        $totalCategories = $products
            ->pluck('productVariant.product.category_id')
            ->filter()
            ->unique()
            ->count();
        return response()->json([
            'filter' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'stats' => [
                'total_product' => $totalProduct,
                'total_quantity_sold' => $totalQuantity,
                'total_categories' => $totalCategories,
                'total_revenue' => number_format($totalRevenue, 2),
            ],
            'products' => $products->map(function ($item) {
                $variant = $item->productVariant;
                $product = optional($variant)->product;

                return [
                    'variant_id' => $item->product_variant_id,
                    'product_name' => optional($product)->productName,
                    'variant' => [
                        'size' => optional($variant)->size,
                        'color' => optional($variant)->color,
                    ],
                    'category' => $product->category->name,
                    'image' => optional($product)->image,
                    'total_quantity' => (int) $item->total_quantity,

                ];
            })
        ]);
    }


}
