<?php

namespace App\Services;

class DiscountService
{
    public function discountedPrice($product)
    {
        if (!$product->discount) {
            return $product->price ?? null;
        }

        $now = now();

        if (
            ($product->discount->start_date && $now->lt($product->discount->start_date)) ||
            ($product->discount->end_date && $now->gt($product->discount->end_date))
        ) {
            return (float) $product->price;
        }

        if ($product->discount->type === 'percent') {
            return (float) ($product->price - ($product->price * $product->discount->amount / 100));
        }

        return (float) max(0, $product->price - $product->discount->amount);
    }
}