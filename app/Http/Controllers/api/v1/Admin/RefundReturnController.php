<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Events\ReturnStatusUpdated;
use App\Http\Controllers\Controller;
use App\Mail\ReturnCompletedMail;
use App\Models\Refund;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use App\Services\RefundService;
use App\Traits\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use ImageKit\ImageKit;

class RefundReturnController extends Controller
{
    use NotifiesUsers;

    protected $RefundService;

    public function __construct(RefundService $refundService)
    {
        $this->RefundService = $refundService;
    }

    private const REFUND_METHODS = [
        'original_payment',
        'store_credit',
        'khqr',
        'bank_transfer',
    ];

    /**
     * Admin authorization check
     */
    private function ensureAdmin(): void
    {
        $user = Auth::user();

        abort_if(
            !$user || !in_array($user->role ?? null, ['admin', 'super-admin'], true),
            403,
            'Unauthorized.'
        );
    }

    /**
     * CUSTOMER: Submit KHQR refund information.
     */
    public function khqrInfo(Request $request, $id)
    {
        $request->validate([
            'khqr_receiver_number' => ['required', 'string', 'max:100'],
            'khqr_account_name' => ['required', 'string', 'max:255'],
            'khqr_qr_code' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        $return = ReturnOrder::with(['user', 'order.payment', 'refund', 'items'])
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$return) {
            return response()->json(['message' => 'Return order not found.'], 404);
        }

        $hasRefundItem = $return->items->contains('return_type', ReturnItem::TYPE_REFUND);
        if (!$hasRefundItem) {
            return response()->json(['message' => 'This return order does not contain any refund items.'], 400);
        }

        if (!in_array($return->status, [ReturnOrder::STATUS_RECEIVED, ReturnOrder::STATUS_INSPECTED], true)) {
            return response()->json(['message' => 'Return must be received or inspected before submitting KHQR information.'], 400);
        }

        $refund = $return->refund;

        if ($refund && $refund->status === Refund::STATUS_COMPLETED) {
            return response()->json(['message' => 'This refund has already been completed.'], 400);
        }

        try {
            $qrCodeUrl = $refund?->khqr_qr_code;

            if ($request->hasFile('khqr_qr_code') && $request->file('khqr_qr_code')->isValid()) {
                $file = $request->file('khqr_qr_code');

                $imageKit = new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                );

                $upload = $imageKit->uploadFile([
                    'file' => base64_encode(file_get_contents($file->getRealPath())),
                    'fileName' => 'refund-khqr-' . $return->id . '-' . time() . '.' . $file->getClientOriginalExtension(),
                    'folder' => '/refund-khqr',
                ]);

                if (isset($upload->error)) {
                    Log::error('KHQR QR upload failed: ' . ($upload->error->message ?? 'Unknown error'));

                    return response()->json(['message' => 'Failed to upload KHQR QR code.'], 500);
                }

                $qrCodeUrl = $upload->result->url;
            }

            $amount = $this->RefundService->calculateRefundAmount($return);

            // Explicit array definition ensuring method & provider are NEVER null
            $refundPayload = [
                'return_order_id' => $return->id,
                'order_id' => $return->order_id,
                'user_id' => $return->user_id,
                'payment_id' => $return->order->payment?->id,
                'amount' => $amount,
                'method' => 'khqr',
                'provider' => 'bakong',
                'status' => Refund::STATUS_PENDING,
                'khqr_receiver_number' => $request->khqr_receiver_number,
                'khqr_account_name' => $request->khqr_account_name,
                'khqr_qr_code' => $qrCodeUrl,
            ];

            if (!$refund) {
                $refundPayload['refund_reference'] = 'REF-' . strtoupper(Str::random(12));
                $refund = Refund::create($refundPayload);
            } else {
                $refund->update($refundPayload);
            }

            return response()->json([
                'message' => 'KHQR refund information submitted successfully.',
                'data' => [
                    'refund_id' => $refund->id,
                    'return_order_id' => $return->id,
                    'amount' => (float) $refund->amount,
                    'method' => $refund->method,
                    'provider' => $refund->provider,
                    'status' => $refund->status,
                ],
                'khqr' => [
                    'receiver_number' => $refund->khqr_receiver_number,
                    'account_name' => $refund->khqr_account_name,
                    'qr_code' => $refund->khqr_qr_code,
                    'transaction_id' => $refund->khqr_transaction_id,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('KHQR information failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to submit KHQR information.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ADMIN: Process a refund for an inspected return order.
     */
    public function refund(Request $request, $id)
    {
        $this->ensureAdmin();

        $request->merge([
            'refund_method' => strtolower(trim((string) $request->input('refund_method'))),
        ]);

        $request->validate([
            'refund_method' => ['nullable', Rule::in(self::REFUND_METHODS)],
        ]);

        try {
            $return = DB::transaction(function () use ($request, $id) {
                $return = ReturnOrder::with([
                    'items.orderItem.productVariant',
                    'user',
                    'order.items',
                    'order.payment',
                    'refund',
                ])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                $hasRefundItem = $return->items->contains('return_type', ReturnItem::TYPE_REFUND);
                if (!$hasRefundItem) {
                    throw new \RuntimeException('This return request does not contain any refund items.');
                }

                if (!in_array($return->status, [ReturnOrder::STATUS_RECEIVED, ReturnOrder::STATUS_INSPECTED], true)) {
                    throw new \RuntimeException('Return must be received or inspected before processing refund.');
                }

                $refund = $return->refund;

                if ($refund && $refund->status === Refund::STATUS_COMPLETED) {
                    throw new \RuntimeException('This return has already been refunded.');
                }

                $amount = $this->RefundService->calculateRefundAmount($return);

                if ($amount <= 0) {
                    throw new \RuntimeException('Refund amount must be greater than zero.');
                }

                $method = $request->refund_method;

                if ($method === 'khqr') {
                    if (!$refund) {
                        throw new \RuntimeException('Customer has not submitted KHQR refund information.');
                    }

                    if (empty($refund->khqr_receiver_number) || empty($refund->khqr_account_name)) {
                        throw new \RuntimeException('Incomplete KHQR refund information.');
                    }
                }

                $provider = match ($method) {
                    'store_credit' => 'internal_wallet',
                    'khqr' => 'bakong',
                    'bank_transfer' => 'bank_transfer',
                    'original_payment' => $return->order->payment?->payment_method ?? 'manual',
                    default => 'manual',
                };

                if (!$refund) {
                    $refund = Refund::create([
                        'return_order_id' => $return->id,
                        'return_item_id' => $return->items->first()->id,
                        'order_id' => $return->order_id,
                        'user_id' => $return->user_id,
                        'payment_id' => $return->order->payment?->id,
                        'amount' => $amount,
                        'method' => $method,
                        'provider' => $provider,
                        'status' => Refund::STATUS_PROCESSING,
                        'refund_reference' => 'REF-' . strtoupper(Str::random(12)),
                    ]);
                } else {
                    $refund->update([
                        'amount' => $amount,
                        'method' => $method,
                        'provider' => $provider,
                        'status' => Refund::STATUS_PROCESSING,
                    ]);
                }

                $this->processRefund($return, $refund);

                return $return;
            });

            if ($return->user) {
                $this->initializeNotification(
                    $return->user,
                    'Refund Processing',
                    'Your refund for order #' . ($return->order->order_number ?? $return->order_id) . ' is being processed.',
                    'return order',
                    'shopping-bag',
                    "/account/returns/{$return->id}"
                );
            }
            broadcast(new ReturnStatusUpdated(
                $return->id,
                Refund::STATUS_PROCESSING,
                'Your refund for order #' .
                ($return->order->order_number ?? $return->order_id) .
                ' is being processed.'
            ));
            return response()->json([
                'message' => 'Refund processed successfully.',
                'data' => $return->fresh(['refund', 'items', 'order']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Refund processing failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to process refund.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Advance a refund's status/provider based on its method.
     */
    private function processRefund(ReturnOrder $return, Refund $refund): void
    {
        $method = $refund->method;
        $amount = (float) $refund->amount;

        if ($method === 'store_credit') {
            $return->user->increment('wallet_balance', $amount);

            $refund->update([
                'provider' => 'internal_wallet',
                'status' => Refund::STATUS_COMPLETED,
                'processed_at' => now(),
            ]);

            return;
        }

        if ($method === 'original_payment') {
            $refund->update([
                'provider' => $return->order->payment?->payment_method ?? 'manual',
                'status' => Refund::STATUS_PROCESSING,
            ]);

            return;
        }

        if ($method === 'khqr') {
            $refund->update([
                'provider' => 'bakong',
                'status' => Refund::STATUS_PROCESSING,
            ]);

            return;
        }

        if ($method === 'bank_transfer') {
            $refund->update([
                'provider' => 'bank_transfer',
                'status' => Refund::STATUS_PROCESSING,
            ]);

            return;
        }

        throw new \RuntimeException('Unsupported refund method.');
    }

    /**
     * ADMIN: Complete a refund return (close it out after refund is completed).
     */
    public function completeRefund($id)
    {
        $this->ensureAdmin();

        try {
            $return = DB::transaction(function () use ($id) {
                $return = ReturnOrder::with(['user', 'order', 'items.refund', 'items'])
                    ->lockForUpdate()
                    ->find($id);

                if (!$return) {
                    throw new \RuntimeException('Return order not found.');
                }

                $hasRefundItem = $return->items->contains('return_type', ReturnItem::TYPE_REFUND);
                if (!$hasRefundItem) {
                    throw new \RuntimeException('This return request does not contain any refund items.');
                }

                if (!$return->refund) {
                    throw new \RuntimeException('Refund record not found.');
                }

                if ($return->refund->status === Refund::STATUS_COMPLETED) {
                    throw new \RuntimeException('This refund has already been completed.');
                }

                $return->refund->update([
                    'status' => Refund::STATUS_COMPLETED,
                    'processed_at' => now(),
                ]);

                return $return->fresh(['items.refund', 'items']);
            });

            try {
                if ($return->user?->email) {
                    Mail::to($return->user->email)->send(
                        new ReturnCompletedMail($return->fresh(['refund', 'items']))
                    );
                }
            } catch (\Exception $e) {
                Log::error('Return completion email failed: ' . $e->getMessage());
            }
            broadcast(new ReturnStatusUpdated(
                $return->id,
                Refund::STATUS_COMPLETED,
                'Your refund for order #' .
                ($return->order->order_number ?? $return->order_id) .
                ' has been completed.'
            ));
            return response()->json([
                'message' => 'Refund completed successfully.',
                'data' => $return->fresh(['items.refund', 'items']),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Complete refund return failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to complete refund return.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}