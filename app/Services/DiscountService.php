<?php

namespace App\Services;

class DiscountService
{
    /**
     * Returns the discounted price if a discount is active, otherwise returns null.
     */
    public function discountedPrice($product): ?float
    {
        // 1. If no discount object exists, return null (previously 0)
        if (!$product->discount) {
            return null;
        }

        $now = now();

        // 2. If the discount is expired or hasn't started yet, return null
        if (
            ($product->discount->start_date && $now->lt($product->discount->start_date)) ||
            ($product->discount->end_date && $now->gt($product->discount->end_date))
        ) {
            return null;
        }

        // 3. Calculate Percentage Discount
        if ($product->discount->type === 'percent') {
            return (float) ($product->price - ($product->price * $product->discount->amount / 100));
        }

        // 4. Calculate Fixed Discount (ensuring it doesn't go below 0)
        // Note: Using ?? 0 here just in case the amount field is missing in the DB
        return (float) max(0, $product->price - ($product->discount->amount ?? 0));
    }
}