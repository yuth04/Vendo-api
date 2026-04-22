<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
class OrderHistoryExport implements FromCollection,  WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */

    public function collection()
    {
        $order = Order::with('user', 'items', 'payment')->latest()->get();
        return $order->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'data' => $order->created_at->format('Y-m-d'),
                'first_name' => $order->user->first_name,
                'last_name' => $order->user->last_name,
                'email' => $order->user->email,
                'total_price' => $order->total_price,
                'items_count' => $order->items->count(),
                'status' => $order->status,
                'payment' => $order->payment->status

            ];
        });

    }
    public function headings(): array
    {
        return [
            'Order Number',
            'Date',
            'First Name',
            'Last Name',
            'Email',
            'Total Price',
            'Items Count',
            'Status',
            'Payment Status',
        ];
    }


}
