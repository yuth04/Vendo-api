<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class ProductExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Product::with(['category', 'brand', 'variants'])
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($product) {
                return [
                    $product->id,
                    $product->productName,
                    $product->category->name ?? 'N/A',
                    $product->brand->name ?? 'N/A',
                    $product->price,
                    $product->variants->sum('stock') ?? 0,
                    $product->status ?? 'active',
                    $product->variants->count(),
                    $product->created_at->format('Y-m-d'),
                ];
            });


    }
    public function headings(): array
    {
        return [
            'ID',
            'Product Name',
            'Category',
            'Brand',
            'Price',
            'Stock',
            'Status',
            'Variants Count',
            'Created Date',
        ];
    }
}
