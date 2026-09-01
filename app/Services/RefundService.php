<?php

namespace App\Services;

use App\Models\ReturnOrder;

class RefundService
{
    /**
     * Calculate proportional refund amount for a return order.
     */
    public function calculateRefundAmount(ReturnOrder $return): float
    {
        $return->loadMissing('items.orderItem', 'order');

        $order = $return->order;
        if (!$order) {
            return 0.00;
        }

        $orderSubtotal = (float) $order->sub_total_price;
        $couponDiscount = (float) ($order->coupon_discount ?? 0);

        $calculatedRefund = $return->items->sum(function ($item) use ($orderSubtotal, $couponDiscount) {
            if (!$item->orderItem) {
                return 0;
            }

            $itemTotal = $item->quantity * (float) $item->orderItem->price;

            if ($orderSubtotal > 0 && $couponDiscount > 0) {
                $itemCouponShare = ($itemTotal / $orderSubtotal) * $couponDiscount;

                return max(0, $itemTotal - $itemCouponShare);
            }

            return $itemTotal;
        });

        return min(round($calculatedRefund, 2), (float) $order->total_price);
    }
}