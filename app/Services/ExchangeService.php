<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\ProductVariant;

class ExchangeService
{

    public function calculatePriceDifference(
        OrderItem $orderItem,
        ProductVariant $replacement,
        int $originalQuantity,
        int $exchangeQuantity
    ): float {
        $originalAmount = (float) $orderItem->price * $originalQuantity;

        $replacementUnitPrice = (float) ($replacement->product?->price ?? 0);
        $replacementAmount = $replacementUnitPrice * $exchangeQuantity;

        return round($replacementAmount - $originalAmount, 2);
    }
}