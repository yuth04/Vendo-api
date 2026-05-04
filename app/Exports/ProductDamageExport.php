<?php

namespace App\Exports;

use App\Models\ProductDamage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductDamageExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return ProductDamage::with(['product.variants', 'user'])->get()->map(function ($damage) {
            return [
                'Prodcut' => $damage->product->productName ?? 'N?A',
                'Reason' => $damage->reason,
                'Quantity' => $damage->quantity,
                'Current_stock' => $damage->product->variants->sum('stock') ?? 0,
                'Status' => $damage->status,
                'Reported By' => ($damage->user->first_name ?? 'Unknown') . ' ' . ($damage->user->last_name ?? ''),
                'Created_at' => $damage->created_at
            ];
        });

    }
    public function headings(): array
    {
        return [
            'Product',
            'Reason',
            'Quantity',
            'Current Stock',
            'Status',
            'Reported By',
            'Created at'
        ];
    }
}
