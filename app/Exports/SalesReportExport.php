<?php

namespace App\Exports;

use App\Models\Order;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesReportExport implements FromCollection, WithHeadings
{
    /**
     * @return \Illuminate\Support\Collection
     */
    protected $startDate, $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection()
    {
        $query = Order::with('payment');

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('created_at', [
                Carbon::parse($this->startDate)->startOfDay(),
                Carbon::parse($this->endDate)->endOfDay()
            ]);
        }

        return $query->get()->map(function ($order) {
            return [
                'Order ID' => '#' . $order->order_number,
                'Date' => $order->created_at->format('Y-m-d'),
                'Total' => $order->total_price,
                'Discount' => $order->discount_amount,
                'Shipping' => $order->shipping_fee,
                'Payment Method' => optional($order->payment)->payment_method,
                'Payment Status' => optional($order->payment)->status,
                'Order Status' => $order->status,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Order ID',
            'Date',
            'Total',
            'Discount',
            'Shipping',
            'Payment Method',
            'Payment Status',
            'Order Status',
        ];
    }
}
