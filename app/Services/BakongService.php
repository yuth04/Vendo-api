<?php

namespace App\Services;

use App\Mail\AdminOrderPlaced;
use App\Mail\OrderPlaced;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;

class BakongService
{
    /**
     * Generate or retrieve an active KHQR payment payload for an order.
     *
     * @param Order $order
     * @return array
     * @throws \Exception
     */
    public function generateKhqrPayment(Order $order): array
    {
        // 1. Lock check: Prevent regenerating QR for paid/confirmed orders
        if (in_array($order->status, ['confirmed', 'paid', Order::STATUS_CONFIRMED ?? 'confirmed'])) {
            throw new \Exception('This order has already been paid.');
        }

        // 2. Check Laravel Cache first for fast response
        $cacheKey = "khqr_payload_order_{$order->id}";
        $cachedPayload = Cache::get($cacheKey);
        if ($cachedPayload) {
            return $cachedPayload;
        }

        // 3. Find latest payment attempt for this order
        $existingPayment = Payment::where('order_id', $order->id)
            ->where('payment_method', 'KHQR')
            ->latest()
            ->first();

        // 4. Return existing pending payment if it is still valid
        if ($existingPayment && $existingPayment->status === 'pending' && now()->isBefore($existingPayment->khqr_expires_at)) {
            $response = [
                'qr_string' => $existingPayment->payment_reference,
                'md5' => $existingPayment->transaction_id,
                'khqr_expires_at' => Carbon::parse($existingPayment->khqr_expires_at)->format('d-m-Y g:i:s A'),
                'amount' => (float) $existingPayment->amount,
                'currency' => 'USD',
                'status' => $existingPayment->status,
            ];

            Cache::put($cacheKey, $response, Carbon::parse($existingPayment->khqr_expires_at));
            return $response;
        }

        // 5. Generate a new KHQR code (Recommended window: 3 minutes)
        $expiresAt = now()->addMinutes(3);
        $expirationTimestamp = strval($expiresAt->getTimestampMs());

        $individualInfo = IndividualInfo::withOptionalArray(
            config('services.bakong.account_id'),
            config('services.bakong.merchant_name'),
            config('services.bakong.merchant_city'),
            [
                'currency' => KHQRData::CURRENCY_USD,
                'amount' => (float) $order->total_price,
                'expirationTimestamp' => $expirationTimestamp,
            ]
        );

        $result = BakongKHQR::generateIndividual($individualInfo);

        if (($result->status['code'] ?? null) !== 0) {
            throw new \Exception('Failed to generate Bakong KHQR code.');
        }

        // Update  or create  payment
        if ($existingPayment) {
            $payment = $existingPayment;
            $payment->update([
                'amount' => $order->total_price,
                'transaction_id' => $result->data['md5'],
                'payment_reference' => $result->data['qr'],
                'status' => 'pending',
                'khqr_expires_at' => $expiresAt,
            ]);
        } else {
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'KHQR',
                'amount' => $order->total_price,
                'transaction_id' => $result->data['md5'],
                'payment_reference' => $result->data['qr'],
                'status' => 'pending',
                'khqr_expires_at' => $expiresAt,
            ]);
        }

        $response = [
            'qr_string' => $payment->payment_reference,
            'md5' => $payment->transaction_id,
            'khqr_expires_at' => $expiresAt->format('d-m-Y g:i:s A'),
            'amount' => (float) $payment->amount,
            'currency' => 'USD',
            'status' => $payment->status,
        ];

        // Store payload in cache until expiration time
        Cache::put($cacheKey, $response, $expiresAt);

        return $response;
    }
    public function checkPaymentStatus(Order $order): string
    {
        // 1. Fast path: Return cached terminal status immediately
        $terminalStateKey = "order_status_terminal_{$order->id}";
        if ($cachedStatus = Cache::get($terminalStateKey)) {
            return $cachedStatus;
        }

        // 2. Throttle Bakong API checks (2-second cooldown to avoid rate-limiting)
        $throttleKey = "bakong_poll_lock_{$order->id}";
        if (Cache::has($throttleKey)) {
            return 'pending';
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('payment_method', 'KHQR')
            ->latest()
            ->first();

        if (!$payment) {
            return 'pending';
        }

        if ($payment->status === 'paid') {
            Cache::put($terminalStateKey, 'paid', now()->addHours(24));
            return 'paid';
        }

        if ($payment->status === 'expired' || $order->status === $this->statusCancelled()) {
            Cache::put($terminalStateKey, 'expired', now()->addHours(24));
            return 'expired';
        }

        // Apply a short cooldown lock prior to outgoing HTTP call
        Cache::put($throttleKey, true, 2);

        try {
            $bakong = new BakongKHQR(config('services.bakong.token'));
            $result = $bakong->checkTransactionByMD5($payment->transaction_id);

            $res = json_decode(json_encode($result), true);

            $responseCode = $res['result']['responseCode']
                ?? $res['responseCode']
                ?? $res['status']['code']
                ?? null;

            $transactionData = $res['result']['data'] ?? $res['data'] ?? null;

            if ($responseCode === 0 && !empty($transactionData)) {
                $paidAmount = (float) ($transactionData['amount'] ?? 0);
                $paidCurrency = strtoupper($transactionData['currency'] ?? '');
                $expectedAmount = (float) $order->total_price;
                $expectedCurrency = 'USD';

                // Verify amount and currency
                if (abs($paidAmount - $expectedAmount) > 0.001 || $paidCurrency !== $expectedCurrency) {
                    Log::warning('Bakong Payment Mismatch', [
                        'order_id' => $order->id,
                        'expected' => "{$expectedAmount} {$expectedCurrency}",
                        'received' => "{$paidAmount} {$paidCurrency}",
                    ]);

                    return 'pending';
                }
                $wasJustConfirmed = DB::transaction(function () use ($payment, $order) {
                    $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();
                    $order = Order::where('id', $order->id)->lockForUpdate()->first();

                    if ($payment->status !== 'paid') {
                        $payment->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        $order->update([
                            'status' => $this->statusConfirmed(),
                            'payment_transaction_id' => $payment->transaction_id,
                        ]);

                        return true;
                    }

                    return false;
                });

                // Clear temporary QR cache and mark as paid
                Cache::forget("khqr_payload_order_{$order->id}");
                Cache::forget($throttleKey);
                Cache::put($terminalStateKey, 'paid', now()->addHours(24));

                if ($wasJustConfirmed) {
                    $this->notifyOrderConfirmed($order->fresh());
                }

                return 'paid';
            }
        } catch (\Throwable $e) {
            Log::error('Bakong API Exception', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        // Auto-cancel & restore inventory if payment timer expired
        if ($payment->khqr_expires_at && now()->isAfter($payment->khqr_expires_at)) {
            $this->cancelOrderAndRestoreStock($order, $payment, 'KHQR time expired');

            Cache::forget("khqr_payload_order_{$order->id}");
            Cache::forget($throttleKey);
            Cache::put($terminalStateKey, 'expired', now()->addHours(24));

            return 'expired';
        }

        return 'pending';
    }

    /**
     *
     * @param Order $order
     */
    private function notifyOrderConfirmed(Order $order): void
    {
        if (!$order->user) {
            return;
        }

        try {
            Notification::create([
                'user_id' => $order->user_id,
                'title' => 'Order Confirmed',
                'message' => "Your order #{$order->order_number} has been placed successfully.",
                'type' => 'order_confirmed',
                'icon' => 'shopping-bag',
                'url' => "/account/orders/{$order->id}",
            ]);
        } catch (\Throwable $e) {
            Log::error('Order confirmed notification failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);
        }

        if ($order->user->email) {
            try {
                Mail::to($order->user->email)->send(new OrderPlaced($order));
                Mail::to(config('mail.admin_email'))->send(new AdminOrderPlaced($order));
            } catch (\Throwable $e) {
                Log::error('Order confirmed mail failed', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
    /**
     * Cancel expired order and restore product stock safely.
     *
     * @param Order $order
     * @param Payment $payment
     * @param string $reason
     */
    private function cancelOrderAndRestoreStock(Order $order, Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($order, $payment, $reason) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            if ($payment && $payment->status === 'pending') {
                $payment->update(['status' => 'expired']);

                $order->update([
                    'status' => $this->statusCancelled(),
                    'cancelled_at' => now(),
                    'note' => $order->note ? $order->note . " | Cancelled: {$reason}" : "Cancelled: {$reason}",
                ]);

                $order->loadMissing('items');

                foreach ($order->items as $item) {
                    if ($item->product_variant_id) {
                        ProductVariant::where('id', $item->product_variant_id)
                            ->increment('qty', $item->quantity);
                    }
                }

                Log::info("Order #{$order->id} cancelled and stock returned.", ['reason' => $reason]);
            }
        });
    }

    private function statusConfirmed(): string
    {
        return defined(Order::class . '::STATUS_CONFIRMED')
            ? Order::STATUS_CONFIRMED
            : 'confirmed';
    }
    private function statusCancelled(): string
    {
        return defined(Order::class . '::STATUS_CANCELLED')
            ? Order::STATUS_CANCELLED
            : 'cancelled';
    }
}