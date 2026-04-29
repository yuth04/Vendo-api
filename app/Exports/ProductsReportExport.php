<?php

namespace App\Exports;

use App\Models\OrderItem;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductsReportExport implements FromCollection, WithHeadings
{
    protected $startDate, $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $query = OrderItem::with(['productVariant.product.category', 'order'])
            ->whereHas('order', function ($q) {
                $q->whereIn('status', ['completed', 'delivered']);
            });

        if ($this->startDate && $this->endDate) {
            $start = $this->startDate;
            $end = $this->endDate;

            $query->whereHas('order', function ($q) use ($start, $end) {
                $q->whereBetween('created_at', [
                    Carbon::parse($start)->startOfDay(),
                    Carbon::parse($end)->endOfDay()
                ]);
            });
        }

        $products = $query
            ->select('product_variant_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(price * quantity) as total_revenue')
            ->groupBy('product_variant_id')
            ->orderByDesc('total_quantity') // 🔥 best selling first
            ->get();

        return $products->map(function ($item) {
            $variant = $item->productVariant;
            $product = optional($variant)->product;

            return [
                'Variant ID' => $item->product_variant_id,
                'Product Name' => optional($product)->productName,
                'Category' => optional($product->category)->name,
                'Size' => optional($variant)->size,
                'Color' => optional($variant)->color,
                'Total Quantity' => (int) $item->total_quantity,
                'Total Revenue' => $item->total_revenue, // ✅ keep raw number
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Variant ID',
            'Product Name',
            'Category',
            'Size',
            'Color',
            'Total Quantity',
            'Total Revenue',
        ];
    }
}
