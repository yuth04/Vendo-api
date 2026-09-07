<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReturnCompletedMail;
use App\Mail\ReturnReceivedMail;
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
    // Admin: Get Returnable Item for Exchange
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
                    'image' => $variant->images()->first()?->image ?? null,
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
            $return = ReturnOrder::with([
                'items.orderItem.productVariant',
                'items.exchange',
                'user',
                'order',
            ])
                ->lockForUpdate()
                ->find($id);

            if (!$return) {
                throw new \RuntimeException('Return order not found.');
            }

            $exchangeItems = $return->items
                ->where('return_type', ReturnItem::TYPE_EXCHANGE);

            if ($exchangeItems->isEmpty()) {
                throw new \RuntimeException('This return request does not contain any exchange items.');
            }

            if (!in_array($return->status, [ReturnOrder::STATUS_RECEIVED, ReturnOrder::STATUS_INSPECTED], true)) {
                throw new \RuntimeException('Return item(s) must be received before exchange processing.');
            }

            $replacementVariants = [];

            // STEP 1: Validate missing data, lock product variants, and check stock
            foreach ($exchangeItems as $item) {
                if (!$item->replacement_variant_id) {
                    throw new \RuntimeException("Replacement variant is missing for return item #{$item->id}.");
                }

                $replacement = ProductVariant::lockForUpdate()->find($item->replacement_variant_id);

                if (!$replacement) {
                    throw new \RuntimeException("Replacement product variant not found for return item #{$item->id}.");
                }

                $quantity = $item->exchange_quantity ?? $item->quantity;
                $existingExchange = $item->exchange;

                if (!$existingExchange || !$existingExchange->stock_reserved) {
                    if ($replacement->stock < $quantity) {
                        throw new \RuntimeException("Replacement product (Variant #{$replacement->id}) is out of stock.");
                    }
                }

                // Cache loaded variant for STEP 2
                $replacementVariants[$item->id] = $replacement;
            }

            // STEP 2: Create/Update Exchange record and reserve stock
            foreach ($exchangeItems as $item) {
                $replacement = $replacementVariants[$item->id];
                $quantity = $item->exchange_quantity ?? $item->quantity;
                $priceDifference = (float) ($item->exchange_price_difference ?? 0);

                // Re-use already eager-loaded relationship
                $exchange = $item->exchange;

                if (!$exchange) {
                    $exchange = Exchange::create([
                        'return_order_id' => $return->id,
                        'return_item_id'  => $item->id,
                        'order_id'        => $return->order_id,
                        'user_id'         => $return->user_id,
                        'status'          => Exchange::STATUS_PROCESSING,
                        'stock_reserved'  => false,
                        'price_difference'=> round($priceDifference, 2),
                        'processing_at'   => now(),
                    ]);
                }

                if (in_array($exchange->status, [
                    Exchange::STATUS_SHIPPED,
                    Exchange::STATUS_RECEIVED,
                    Exchange::STATUS_COMPLETED,
                ], true)) {
                    continue;
                }

                $exchange->update([
                    'status'           => Exchange::STATUS_PROCESSING,
                    'processing_at'    => $exchange->processing_at ?? now(),
                    'price_difference' => round($priceDifference, 2),
                ]);

                if (!$exchange->stock_reserved) {
                    $replacement->decrement('stock', $quantity);

                    $exchange->update([
                        'stock_reserved' => true,
                    ]);
                }
            }

            return $return->fresh([
                'items.exchange',
                'items.replacementVariant.product',
                'user',
                'order',
            ]);
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
            'data'    => $return,
        ]);

    } catch (\Exception $e) {
        Log::error('Exchange processing failed', [
            'return_order_id' => $id,
            'user_id'         => Auth::id(),
            'error'           => $e->getMessage(),
        ]);

        return response()->json([
            'message' => 'Failed to process exchange.',
            'error'   => $e->getMessage(),
        ], 400);
    }
}
    // public function processExchange(Request $request, $id)
    // {
    //     $this->ensureAdmin();

    //     try {

    //         $return = DB::transaction(function () use ($id) {

    //             $return = ReturnOrder::with([
    //                 'items.orderItem.productVariant',
    //                 'items.exchange',
    //                 'user',
    //                 'order',
    //             ])
    //                 ->lockForUpdate()
    //                 ->find($id);

    //             if (!$return) {
    //                 throw new \RuntimeException('Return order not found.');
    //             }

    //             $exchangeItems = $return->items
    //                 ->where('return_type', ReturnItem::TYPE_EXCHANGE);

    //             if ($exchangeItems->isEmpty()) {
    //                 throw new \RuntimeException(
    //                     'This return request does not contain any exchange items.'
    //                 );
    //             }

    //             // Return must be received or inspected
    //             if (
    //                 !in_array(
    //                     $return->status,
    //                     [
    //                         ReturnOrder::STATUS_RECEIVED,
    //                         ReturnOrder::STATUS_INSPECTED
    //                     ],
    //                     true
    //                 )
    //             ) {
    //                 throw new \RuntimeException(
    //                     'Return item(s) must be received before exchange processing.'
    //                 );
    //             }

    //             /*
    //              * STEP 1
    //              * Check stock for every exchange item
    //              */
    //             foreach ($exchangeItems as $item) {

    //                 if (!$item->replacement_variant_id) {
    //                     throw new \RuntimeException(
    //                         "Replacement variant is missing for return item #{$item->id}."
    //                     );
    //                 }

    //                 $replacement = ProductVariant::lockForUpdate()
    //                     ->find($item->replacement_variant_id);

    //                 if (!$replacement) {
    //                     throw new \RuntimeException(
    //                         "Replacement product variant not found for return item #{$item->id}."
    //                     );
    //                 }

    //                 $quantity = $item->exchange_quantity ?? $item->quantity;

    //                 /*
    //                  * Only check stock if stock has not already
    //                  * been reserved.
    //                  */
    //                 $existingExchange = $item->exchange;

    //                 if (
    //                     !$existingExchange ||
    //                     !$existingExchange->stock_reserved
    //                 ) {
    //                     if ($replacement->stock < $quantity) {
    //                         throw new \RuntimeException(
    //                             "Replacement product (Variant #{$replacement->id}) is out of stock."
    //                         );
    //                     }
    //                 }
    //             }

    //             /*
    //              * STEP 2
    //              * Create/update Exchange for EACH ReturnItem
    //              */
    //             foreach ($exchangeItems as $item) {

    //                 $replacement = ProductVariant::lockForUpdate()
    //                     ->find($item->replacement_variant_id);

    //                 $quantity = $item->exchange_quantity ?? $item->quantity;

    //                 /*
    //                  * Calculate price difference for this item
    //                  */
    //                 $priceDifference = (float) (
    //                     $item->exchange_price_difference ?? 0
    //                 );

    //                 /*
    //                  * Get exchange for THIS return item
    //                  */
    //                 $exchange = Exchange::where(
    //                     'return_item_id',
    //                     $item->id
    //                 )->first();

    //                 /*
    //                  * Create exchange if it doesn't exist
    //                  */
    //                 if (!$exchange) {

    //                     $exchange = Exchange::create([
    //                         'return_order_id' => $return->id,
    //                         'return_item_id' => $item->id,
    //                         'order_id' => $return->order_id,
    //                         'user_id' => $return->user_id,
    //                         'status' => Exchange::STATUS_PROCESSING,
    //                         'stock_reserved' => false,
    //                         'price_difference' => round(
    //                             $priceDifference,
    //                             2
    //                         ),
    //                         'processing_at' => now(),
    //                     ]);
    //                 }
    //                 if (
    //                     in_array($exchange->status, [
    //                         Exchange::STATUS_SHIPPED,
    //                         Exchange::STATUS_RECEIVED,
    //                         Exchange::STATUS_COMPLETED,
    //                     ], true)
    //                 ) {
    //                     continue;
    //                 }
    //                 $exchange->update([
    //                     'status' => Exchange::STATUS_PROCESSING,
    //                     'processing_at' => $exchange->processing_at ?? now(),
    //                     'price_difference' => round(
    //                         $priceDifference,
    //                         2
    //                     ),
    //                 ]);
    //                 if (!$exchange->stock_reserved) {

    //                     $replacement->decrement(
    //                         'stock',
    //                         $quantity
    //                     );

    //                     $exchange->update([
    //                         'stock_reserved' => true,
    //                     ]);
    //                 }
    //             }

    //             return $return->fresh([
    //                 'items.exchange',
    //                 'items.replacementVariant.product',
    //                 'user',
    //                 'order',
    //             ]);
    //         });

    //         /*
    //          * Notification
    //          */
    //         $this->initializeNotification(
    //             $return->user,
    //             'Exchange Processing',
    //             'Your replacement item for order #' .
    //             $return->order->order_number .
    //             ' is being prepared.',
    //             'return order',
    //             'shopping-bag',
    //             "/account/returns/{$return->id}"
    //         );

    //         return response()->json([
    //             'message' => 'Exchange is now processing.',
    //             'data' => $return,
    //         ]);

    //     } catch (\Exception $e) {

    //         Log::error('Exchange processing failed', [
    //             'return_order_id' => $id,
    //             'user_id' => Auth::id(),
    //             'error' => $e->getMessage(),
    //         ]);

    //         return response()->json([
    //             'message' => 'Failed to process exchange.',
    //             'error' => $e->getMessage(),
    //         ], 400);
    //     }
    // }
    // Admin: Exchange Shipped
    public function markExchangeShipped(Request $request, $id)
    {
        $this->ensureAdmin();
        $request->validate([
            'tracking_number' => [
                'nullable',
                'string',
                'max:255',
            ],
            'carrier' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);
        try {
            $return = DB::transaction(function () use ($id, $request) {
                $return = ReturnOrder::with([
                    'user',
                    'order',
                    'items.exchange',
                ])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException(
                        'Return order not found.'
                    );
                }
                // Get exchange items
                $exchangeItems = $return->items->where(
                    'return_type',
                    ReturnItem::TYPE_EXCHANGE
                );
                if ($exchangeItems->isEmpty()) {
                    throw new \RuntimeException(
                        'No exchange items found for this return order.'
                    );
                }
                // Update each exchange
                foreach ($exchangeItems as $item) {
                    $exchange = $item->exchange;
                    if (!$exchange) {
                        throw new \RuntimeException(
                            "Exchange record not found for return item #{$item->id}."
                        );
                    }
                    if ($exchange->status !== Exchange::STATUS_PROCESSING) {
                        throw new \RuntimeException(
                            "Exchange #{$exchange->id} is not in processing status."
                        );
                    }
                    $exchange->update([
                        'status' => Exchange::STATUS_SHIPPED,
                        'tracking_number' => $request->tracking_number,
                        'carrier' => $request->carrier,
                        'shipped_at' => now(),
                    ]);
                }
                return $return->fresh([
                    'user',
                    'order',
                    'items.exchange',
                ]);
            });
            // Email
            if ($return->user?->email) {
                Mail::to($return->user->email)
                    ->send(new ReturnShippedMail($return));
            }
            // Notification
            $this->initializeNotification(
                $return->user,
                'Exchange Shipped',
                'Your replacement item for order #' .
                $return->order->order_number .
                ' has been shipped.',
                'return order',
                'truck',
                "/account/returns/{$return->id}"
            );
            return response()->json([
                'message' => 'All exchange items have been marked as shipped.',
                'data' => $return,
            ], 200);

        } catch (\Exception $e) {

            Log::error(
                'Exchange shipping failed: ' .
                $e->getMessage(),
                [
                    'return_order_id' => $id,
                    'user_id' => Auth::id(),
                ]
            );

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

            $return = DB::transaction(function () use ($id) {

                $return = ReturnOrder::with([
                    'user',
                    'order',
                    'items.exchange',
                ])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException(
                        'Return order not found.'
                    );
                }
                // Get exchange items
                $exchangeItems = $return->items->where(
                    'return_type',
                    ReturnItem::TYPE_EXCHANGE
                );

                if ($exchangeItems->isEmpty()) {
                    throw new \RuntimeException(
                        'No exchange items found for this return order.'
                    );
                }
                // Mark each exchange as received
                foreach ($exchangeItems as $item) {
                    $exchange = $item->exchange;
                    if (!$exchange) {
                        throw new \RuntimeException(
                            "Exchange record not found for return item #{$item->id}."
                        );
                    }
                    if ($exchange->status != Exchange::STATUS_SHIPPED) {
                        throw new \RuntimeException(
                            "Exchange #{$exchange->id} must be shipped before it can be received."
                        );
                    }
                    $exchange->update([
                        'status' => Exchange::STATUS_RECEIVED,
                        'received_at' => now(),
                    ]);
                }
                \Log::info('Exchange received check', [
                    'exchange_id' => $exchange->id,
                    'status' => $exchange->status,
                    'status_raw' => var_export($exchange->status, true),
                    'return_item_id' => $exchange->return_item_id,
                ]);
                return $return->fresh([
                    'user',
                    'order',
                    'items.exchange',
                ]);
            });
            // Email
            if ($return->user?->email) {
                Mail::to($return->user->email)
                    ->send(new ReturnReceivedMail($return));
            }
            // Notification
            $this->initializeNotification(
                $return->user,
                'Exchange Received',
                'Your replacement item for order #' .
                $return->order->order_number .
                ' has been received.',
                'return order',
                'package-check',
                "/account/returns/{$return->id}"
            );

            return response()->json([
                'message' => 'All exchange items have been marked as received.',
                'data' => $return,
            ], 200);

        } catch (\Exception $e) {

            Log::error(
                'Exchange received failed: ' .
                $e->getMessage(),
                [
                    'return_order_id' => $id,
                    'user_id' => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'Failed to mark exchange as received.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    // Admin: Complete Exchange
    // public function completeExchange($id)
    // {
    //     $this->ensureAdmin();

    //     try {

    //         $return = DB::transaction(function () use ($id) {

    //             $return = ReturnOrder::with([
    //                 'user',
    //                 'order',
    //                 'items.refund',
    //                 'items.exchange',
    //             ])
    //                 ->lockForUpdate()
    //                 ->find($id);

    //             if (!$return) {
    //                 throw new \RuntimeException(
    //                     'Return order not found.'
    //                 );
    //             }

    //             // Get exchange items
    //             $exchangeItems = $return->items->where(
    //                 'return_type',
    //                 ReturnItem::TYPE_EXCHANGE
    //             );

    //             if ($exchangeItems->isEmpty()) {
    //                 throw new \RuntimeException(
    //                     'No exchange items found for this return order.'
    //                 );
    //             }
    //             // Complete every exchange
    //             foreach ($exchangeItems as $item) {

    //                 $exchange = $item->exchange;

    //                 if (!$exchange) {
    //                     throw new \RuntimeException(
    //                         "Exchange record not found for return item #{$item->id}."
    //                     );
    //                 }
    //                 // Customer must receive replacement first.

    //                 if ($exchange->status !== Exchange::STATUS_RECEIVED) {
    //                     throw new \RuntimeException(
    //                         "Exchange #{$exchange->id} must be received before completion."
    //                     );
    //                 }

    //                 $exchange->update([
    //                     'status' => Exchange::STATUS_COMPLETED,
    //                 ]);
    //             }
    //             // Check whether ALL return items are completed.
    //             $allItemsCompleted = $return->items->every(
    //                 function ($item) {

    //                     if (
    //                         $item->return_type ===
    //                         ReturnItem::TYPE_EXCHANGE
    //                     ) {

    //                         return $item->exchange &&
    //                             $item->exchange->status ===
    //                             Exchange::STATUS_COMPLETED;
    //                     }

    //                     if (
    //                         $item->return_type ===
    //                         ReturnItem::TYPE_REFUND
    //                     ) {

    //                         return $item->refund &&
    //                             $item->refund->status ===
    //                             \App\Models\Refund::STATUS_COMPLETED;
    //                     }

    //                     return false;
    //                 }
    //             );
    //             // Complete ReturnOrder only if every item is completed
    //             if ($allItemsCompleted) {
    //                 $return->update([
    //                     'status' => ReturnOrder::STATUS_COMPLETED,
    //                     'completed_at' => now(),
    //                 ]);
    //             }
    //             return $return->fresh([
    //                 'user',
    //                 'order',
    //                 'items.refund',
    //                 'items.exchange',
    //             ]);
    //         });

    //         // Notification
    //         $message = $return->status ===
    //             ReturnOrder::STATUS_COMPLETED
    //             ? 'Your return request for order #' .
    //             $return->order->order_number .
    //             ' has been completed.'

    //             : 'Your exchange for order #' .
    //             $return->order->order_number .
    //             ' has been completed.';

    //         $this->initializeNotification(
    //             $return->user,
    //             'Exchange Completed',
    //             $message,
    //             'return order',
    //             'check-circle',
    //             "/account/returns/{$return->id}"
    //         );
    //         return response()->json([
    //             'message' =>
    //                 $return->status ===
    //                 ReturnOrder::STATUS_COMPLETED
    //                 ? 'Exchange completed and return order completed.'
    //                 : 'Exchange completed successfully.',
    //             'data' => $return,
    //         ], 200);

    //     } catch (\Exception $e) {
    //         Log::error(
    //             'Exchange completion failed: ' .
    //             $e->getMessage(),
    //             [
    //                 'return_order_id' => $id,
    //                 'user_id' => Auth::id(),
    //             ]
    //         );
    //         return response()->json([
    //             'message' => 'Failed to complete exchange.',
    //             'error' => $e->getMessage(),
    //         ], 400);
    //     }
    // }
    public function completeExchange($id)
    {
        $this->ensureAdmin();

        try {

            $return = DB::transaction(function () use ($id) {

                $return = ReturnOrder::with([
                    'user',
                    'order',
                    'items.refund',
                    'items.exchange',
                ])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException(
                        'Return order not found.'
                    );
                }

                // Get exchange items
                $exchangeItems = $return->items->where(
                    'return_type',
                    ReturnItem::TYPE_EXCHANGE
                );

                if ($exchangeItems->isEmpty()) {
                    throw new \RuntimeException(
                        'No exchange items found for this return order.'
                    );
                }

                // Complete every exchange
                foreach ($exchangeItems as $item) {

                    $exchange = $item->exchange;

                    if (!$exchange) {
                        throw new \RuntimeException(
                            "Exchange record not found for return item #{$item->id}."
                        );
                    }

                    // Customer must receive replacement first.
                    if ($exchange->status !== Exchange::STATUS_RECEIVED) {
                        throw new \RuntimeException(
                            "Exchange #{$exchange->id} must be received before completion."
                        );
                    }

                    $exchange->update([
                        'status' => Exchange::STATUS_COMPLETED,
                    ]);
                }

                return $return->fresh([
                    'user',
                    'order',
                    'items.refund',
                    'items.exchange',
                ]);
            });

            // Notification
            $this->initializeNotification(
                $return->user,
                'Exchange Completed',
                'Your exchange for order #' .
                $return->order->order_number .
                ' has been completed.',
                'return order',
                'check-circle',
                "/account/returns/{$return->id}"
            );

            return response()->json([
                'message' => 'Exchange completed successfully.',
                'data' => $return,
            ], 200);

        } catch (\Exception $e) {
            Log::error(
                'Exchange completion failed: ' .
                $e->getMessage(),
                [
                    'return_order_id' => $id,
                    'user_id' => Auth::id(),
                ]
            );
            return response()->json([
                'message' => 'Failed to complete exchange.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}