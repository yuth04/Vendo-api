<?php

namespace App\Exports;

use App\Models\ProductVariant;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductVariantsExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function collection()
    {
        return ProductVariant::with('product')->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Product',
            'Size',
            'Color',
            'Price',
            'Stock',
            'Status',
            'Created Date'
        ];
    }

    public function map($v): array
    {
        return [
            $v->id,
            $v->product->productName ?? '-',
            $v->size,
            $v->color,
            $v->price ?? $v->product->price,
            $v->stock,

            // ✅ Status logic
            match (true) {
                $v->stock == 0 => 'OUT',
                $v->stock <= 2 => 'CRITICAL',
                $v->stock <= 5 => 'LOW',
                default => 'OK'
            },

            $v->created_at->format('Y-m-d'),
        ];
    }
}