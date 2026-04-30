<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $products = Product::with('variants')->get();

        return $products->map(function ($p) {

            $totalStock = $p->variants->sum('stock');
            $lowVariants = $p->variants->where('stock', '<=', 5);

            return [
                'ID' => $p->id,
                'Product Name' => $p->productName,
                'Price' => $p->price,

                // 📦 stock
                'Total Stock' => $totalStock,
                'Low Stock Variants' => $lowVariants->count(),

                // 🚨 status
                'Inventory Status' => match (true) {
                    $totalStock == 0 => 'OUT_OF_STOCK',
                    $totalStock <= 5 => 'CRITICAL_LOW',
                    $totalStock <= 10 => 'LOW',
                    default => 'IN_STOCK'
                },

                // ⚠️ alerts (flatten as string)
                'Alerts' => $lowVariants->map(function ($v) {
                    return "{$v->size}/{$v->color} ({$v->stock})";
                })->implode(', ')
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Product Name',
            'Price',
            'Total Stock',
            'Low Stock Variants',
            'Inventory Status',
            'Alerts'
        ];
    }
}
