<?php

namespace App\Exports;

use App\Models\ReturnOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReturnHistoryExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $returns = ReturnOrder::with(['user', 'items', 'order.payment'])
            ->latest()
            ->get();

        return $returns->map(function ($returnOrder) {
            return [
                'ID' => $returnOrder->id,
                'Order Number' => optional($returnOrder->order)->order_number,
                'Products Count' => $returnOrder->items->count(),
                'Date' => $returnOrder->created_at->format('d-m-Y'),
                'Time' => $returnOrder->created_at->format('h:i:s A'),
                'First Name' => optional($returnOrder->user)->first_name,
                'Last Name' => optional($returnOrder->user)->last_name,
                'Email' => optional($returnOrder->user)->email,
                'Reason' => $returnOrder->reason,
                'Note' => $returnOrder->note,
                'Status' => $returnOrder->status ?? 'N/A',
                'Payment Status' => optional(optional($returnOrder->order)->payment)->status ?? 'N/A',
                'Total Price' => optional($returnOrder->order)->total_price,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Order Number',
            'Products Count',
            'Date',
            'Time',
            'First Name',
            'Last Name',
            'Email',
            'Reason',
            'Note',
            'Status',
            'Payment Status',
            'Total Price',
        ];
    }
}
