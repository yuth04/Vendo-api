<?php

namespace App\Http\Controllers\api\v1\Reports;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function salesReport(Request $request)
    {
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

        // Stats query — only orders with a paid payment (matches dashboard/productsReport)
        $statsQuery = (clone $query)->whereHas('payment', function ($q) {
            $q->where('status', 'paid');
        });

        $totalOrders = (clone $statsQuery)->count();

        $totalEarnings = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.id')
            ->where('payments.status', 'paid')
            ->when($startDate && $endDate, function ($q) use ($startDate, $endDate) {
                $q->whereBetween('orders.created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ]);
            })
            ->sum('payments.amount');

        $totalDiscounts = (clone $statsQuery)->sum('discount_amount');
        $shippingCharges = (clone $statsQuery)->sum('shipping_fee');

        $orders = $query->latest()->get(); // unfiltered list, still shows pending orders in the table

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
                    'date' => $order->created_at->format('d/m/Y'),
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
    public function productsReport(Request $request)
    {
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $query = OrderItem::with(['variant.product.category', 'order']);

        // Only orders with a paid payment (matches dashboard's revenue definition)
        $query->whereHas('order.payment', function ($q) {
            $q->where('status', 'paid');
        });

        // Date filter — same column as dashboard (orders.created_at)
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
            ->pluck('variant.product.category_id')
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
                    'category' => optional(optional($product)->category)->name,
                    'image' => optional($product)->image,
                    'total_quantity' => (int) $item->total_quantity,
                    'total_revenue' => number_format($item->total_revenue, 2),
                ];
            })
        ]);
    }
}
