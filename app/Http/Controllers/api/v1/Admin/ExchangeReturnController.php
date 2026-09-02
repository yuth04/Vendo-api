<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReturnCompletedMail;
use App\Mail\ReturnShippedMail;
use App\Models\Exchange;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use App\Traits\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExchangeReturnController extends Controller
{
    use NotifiesUsers;

    // Admin Authorization
    private function ensureAdmin(): void
    {
        $user = Auth::user();

        abort_if(
            !$user || !in_array($user->role ?? null, ['admin', 'super-admin'], true),
            403,
            'Unauthorized.'
        );
    }


    public function getReturnableItem($orderItemId)
    {
        $user = auth()->user();

        $item = OrderItem::with([
            'order',
            'productVariant.product.variants',
        ])
            ->where('id', $orderItemId)
            ->whereHas('order', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$item) {
            return response()->json([
                'message' => 'Order item not found.'
            ], 404);
        }

        $purchasedVariant = $item->productVariant;

        if (!$purchasedVariant) {
            return response()->json([
                'message' => 'Product variant not found for this order item.'
            ], 404);
        }

        $product = $purchasedVariant->product;

        $replacementVariants = $product->variants
            ->where('stock', '>', 0)
            ->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'size' => $variant->size,
                    'color' => $variant->color,
                    'stock' => $variant->stock,
                    'image'=> $variant->images()->first()?->image ?? null,
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Returnable item retrieved successfully.',

            'order_item' => [
                'id' => $item->id,
                'quantity' => $item->quantity,

                'product' => [
                    'id' => $product->id,
                    'name' => $product->productName,
                ],

                // Variant customer originally purchased
                'purchased_variant' => [
                    'id' => $purchasedVariant->id,
                    'size' => $purchasedVariant->size,
                    'color' => $purchasedVariant->color,
                    
                ],

                // Variants customer can select for exchange
                'replacement_variants' => $replacementVariants,
            ],
        ]);
    }
    // Admin: Process Exchange
    public function processExchange(Request $request, $id)
    {
        $this->ensureAdmin();

        try {
            $return = DB::transaction(function () use ($id) {
                $return = ReturnOrder::with(['items.orderItem.productVariant', 'user', 'order', 'exchange'])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                $exchangeItems = $return->items->where('return_type', ReturnItem::TYPE_EXCHANGE);

                if ($exchangeItems->isEmpty()) {
                    throw new \RuntimeException('This return request does not contain any exchange items.');
                }

                // Check allowed status (Received or Inspected)
                if (!in_array($return->status, [ReturnOrder::STATUS_RECEIVED, ReturnOrder::STATUS_INSPECTED], true)) {
                    throw new \RuntimeException('Return item(s) must be received before exchange processing.');
                }

                // Check every replacement item stock
                foreach ($exchangeItems as $item) {
                    if (!$item->replacement_variant_id) {
                        throw new \RuntimeException("Replacement variant is missing for return item #{$item->id}.");
                    }

                    $replacement = ProductVariant::lockForUpdate()->find($item->replacement_variant_id);

                    if (!$replacement) {
                        throw new \RuntimeException('Replacement product variant not found.');
                    }

                    $quantity = $item->exchange_quantity ?? $item->quantity;

                    if ($replacement->stock < $quantity) {
                        throw new \RuntimeException("Replacement product (Variant #{$replacement->id}) is out of stock.");
                    }
                }

                // Create or update exchange record
                $exchange = $return->exchange;

                if (!$exchange) {
                    $priceDifference = $exchangeItems->sum(function ($item) {
                        return (float) ($item->exchange_price_difference ?? 0);
                    });

                    $exchange = Exchange::create([
                        'return_order_id' => $return->id,
                        'order_id' => $return->order_id,
                        'user_id' => $return->user_id,
                        'status' => Exchange::STATUS_PROCESSING,
                        'price_difference' => round($priceDifference, 2),
                        'processing_at' => now(),
                    ]);
                } else {
                    $exchange->update([
                        'status' => Exchange::STATUS_PROCESSING,
                        'processing_at' => now(),
                    ]);
                }

                // Reserve replacement stock
                foreach ($exchangeItems as $item) {
                    $replacement = ProductVariant::lockForUpdate()->find($item->replacement_variant_id);
                    $quantity = $item->exchange_quantity ?? $item->quantity;

                    $replacement->decrement('stock', $quantity);
                }

                return $return;
            });

            $this->initializeNotification(
                $return->user,
                'Exchange Processing',
                'Your replacement item for order #' . $return->order->order_number . ' is being prepared.',
                'return order',
                'shopping-bag',
                "/account/returns/{$return->id}"
            );

            return response()->json([
                'message' => 'Exchange is now processing.',
                'data' => $return->fresh(['exchange', 'items']),
            ]);
        } catch (\Exception $e) {
            Log::error('Exchange processing failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to process exchange.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    // Admin: Exchange Shipped
    public function markExchangeShipped(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->validate([
            'tracking_number' => ['required', 'string', 'max:100'],
            'carrier' => ['required', 'string', 'max:100'],
        ]);

        try {
            $exchange = DB::transaction(function () use ($request, $id) {
                $return = ReturnOrder::with(['user', 'order', 'exchange', 'items'])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                $hasExchange = $return->items->contains('return_type', ReturnItem::TYPE_EXCHANGE);
                if (!$hasExchange) {
                    throw new \RuntimeException('This return request does not contain exchange items.');
                }

                if (!$return->exchange || $return->exchange->status !== Exchange::STATUS_PROCESSING) {
                    throw new \RuntimeException('Exchange must be processing before shipment.');
                }

                $return->exchange->update([
                    'status' => Exchange::STATUS_SHIPPED,
                    'shipped_at' => now(),
                    'tracking_number' => $request->tracking_number,
                    'carrier' => $request->carrier,
                ]);

                return $return->exchange;
            });

            $return = $exchange->returnOrder->load('user', 'order');

            $this->initializeNotification(
                $return->user,
                'Exchange Shipped',
                'Your replacement item for order #' . $return->order->order_number . ' has been shipped.',
                'return order',
                'shopping-bag',
                "/account/returns/{$return->id}"
            );

            return response()->json([
                'message' => 'Replacement item marked as shipped.',
                'data' => $exchange->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark exchange as shipped.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    // Admin: Exchange Received by Customer
    public function markExchangeReceived($id)
    {
        $this->ensureAdmin();

        try {
            $exchange = DB::transaction(function () use ($id) {
                $return = ReturnOrder::with(['user', 'order', 'exchange', 'items'])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                $hasExchange = $return->items->contains('return_type', ReturnItem::TYPE_EXCHANGE);
                if (!$hasExchange) {
                    throw new \RuntimeException('This return request does not contain exchange items.');
                }

                if (!$return->exchange || $return->exchange->status !== Exchange::STATUS_SHIPPED) {
                    throw new \RuntimeException('Exchange must be shipped before it can be received.');
                }

                $return->exchange->update([
                    'status' => Exchange::STATUS_RECEIVED,
                    'received_at' => now(),
                ]);

                return $return->exchange;
            });

            $return = $exchange->returnOrder->load('user', 'order');

            try {
                if ($return->user?->email) {
                    Mail::to($return->user->email)->send(new ReturnShippedMail($return->fresh(['exchange'])));
                }
            } catch (\Exception $e) {
                Log::error('Return shipped email failed: ' . $e->getMessage());
            }

            $this->initializeNotification(
                $return->user,
                'Exchange Received',
                'Your replacement item for order #' . $return->order->order_number . ' has been received.',
                'return order',
                'shopping-bag',
                "/account/returns/{$return->id}"
            );

            return response()->json([
                'message' => 'Exchange marked as received.',
                'data' => $exchange->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to mark exchange as received.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    // Admin: Complete Exchange
    public function completeExchange($id)
    {
        $this->ensureAdmin();

        try {
            $return = DB::transaction(function () use ($id) {
                $return = ReturnOrder::with(['user', 'order', 'exchange', 'items'])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                $hasExchange = $return->items->contains('return_type', ReturnItem::TYPE_EXCHANGE);
                if (!$hasExchange) {
                    throw new \RuntimeException('This return request does not contain exchange items.');
                }

                if (!$return->exchange || !in_array($return->exchange->status, [Exchange::STATUS_SHIPPED, Exchange::STATUS_RECEIVED], true)) {
                    throw new \RuntimeException('Replacement item must be shipped or received before completing.');
                }

                $return->exchange->update([
                    'status' => Exchange::STATUS_COMPLETED,
                ]);

                $return->update([
                    'status' => ReturnOrder::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                return $return;
            });

            $this->initializeNotification(
                $return->user,
                'Exchange Completed',
                'Your exchange for order #' . $return->order->order_number . ' has been completed.',
                'return order',
                'shopping-bag',
                "/account/returns/{$return->id}"
            );

            try {
                if ($return->user?->email) {
                    Mail::to($return->user->email)->send(
                        new ReturnCompletedMail($return->fresh(['exchange']))
                    );
                }
            } catch (\Exception $e) {
                Log::error('Exchange completion email failed: ' . $e->getMessage());
            }

            return response()->json([
                'message' => 'Exchange completed successfully.',
                'data' => $return->fresh(['exchange', 'items', 'order']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to complete exchange.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}