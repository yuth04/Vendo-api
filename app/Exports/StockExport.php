<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StockExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $products = Product::with(['variants', 'category'])->get();

        // Same status logic as PDF
        $getStatus = function ($stock) {
            if ($stock == 0)
                return 'OUT_OF_STOCK';
            if ($stock <= 10)
                return 'LOW';
            return 'IN_STOCK';
        };

        return $products->flatMap(function ($p) use ($getStatus) {

            $rows = [];

            $totalStock = $p->variants->sum('stock');

            // ✅ PRODUCT ROW
            $rows[] = [
                'Product' => $p->productName,
                'SKU/ID' => 'PROD-' . $p->id,
                'Category' => $p->category->name ?? 'N/A',
                'Stock' => $totalStock,
                'Status' => $getStatus($totalStock),
            ];

            // ✅ VARIANT ROWS
            foreach ($p->variants as $v) {
                $rows[] = [
                    'Product' => '   Color: ' . $v->color . ', Size: ' . $v->size, // indent style
                    'SKU/ID' => 'SKU-' . $v->id,
                    'Category' => '---',
                    'Stock' => $v->stock,
                    'Status' => $getStatus($v->stock),
                ];
            }

            return $rows;
        });
    }

    public function headings(): array
    {
        return [
            'Product',
            'SKU / ID',
            'Category',
            'Stock',
            'Status',
        ];
    }
}