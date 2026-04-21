<?php

namespace App\Http\Controllers\api\v1\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnOrder;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Notification;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'Welcome to the Dashboard API',
            'data' => [
                'total_orders' => Order::count(),
                'total_revenue' => Order::where('status', 'delivered')->sum('total_price'),
                'total_customers' => Order::distinct('id')->count('id'),
                'total_products' => Product::count(),
                'total_brands' => Brand::count(),

                'recent_orders' => Order::latest()->take(5)->get()
            ]
        ]);

    }
    public function orderStat()
    {
        return response()->json([
            'message' => 'Order Status Distribution',
            'data' => [
                'pending' => Order::where('status', 'pending')->count(),
                'confirmed' => Order::where('status', 'confirmed')->count(),
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
        $products = DB::table('products')
            ->join('product_variants', 'products.id', '=', 'product_variants.product_id')
            ->join('order_items', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->select(
                'products.id',
                'products.productName',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.quantity * order_items.price) as revenue')
            )
            ->groupBy('products.id', 'products.productName')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        return response()->json([
            'message' => 'Most Sold Products',
            'data' => $products
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
