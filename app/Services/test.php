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
use Illuminate\Support\Facades\Mail;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;
use Log;
use Request;

class BakongService
{
    public function generateKhqrPayment(Order $order): array
    {
        // Lock QR Code
        if ($order->status === 'confirmed' || $order->status === 'paid') {
            throw new \Exception('This order has already been paid.');
        }
        // Return existing pending payment if still valid
        $existing = Payment::where('order_id', $order->id)
            ->where('payment_method', 'KHQR')
            ->where('status', 'pending')
            ->where('khqr_expires_at', '>', now())
            ->first();

        if ($existing) {
            return [
                'qr_string' => $existing->payment_reference,
                'md5' => $existing->transaction_id,
                'khqr_expires_at' => Carbon::parse($existing->khqr_expires_at)->format('d-m-Y g:i:s A'),
                'amount' => $existing->amount,
                'currency' => 'USD',
            ];
        }
        $expiresAt = now()->addMinutes(5);
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

        if ($result->status['code'] !== 0) {
            throw new \Exception('Failed to generate QR code');
        }

        $payment = Payment::updateOrCreate(
            [
                'order_id' => $order->id,
                'payment_method' => 'KHQR',
            ],
            [
                'amount' => $order->total_price,
                'transaction_id' => $result->data['md5'],
                'payment_reference' => $result->data['qr'],
                'status' => 'pending',
                'khqr_expires_at' => $expiresAt,
            ]
        );

        return [
            'qr_string' => $payment->payment_reference,
            'md5' => $payment->transaction_id,
            'khqr_expires_at' => $expiresAt->format('d-m-Y g:i:s A'),
            'amount' => $payment->amount,
            'currency' => 'USD',
            'status' => $payment->status,
        ];
    }
    // check status 
    public function checkPaymentStatus(Order $order): string
    {
        $payment = Payment::where('order_id', $order->id)
            ->where('payment_method', 'KHQR')
            ->latest()
            ->first();

        if (!$payment) {
            return 'pending';
        }

        if ($payment->status === 'paid') {
            return 'paid';
        }

        try {
            $bakong = new BakongKHQR(config('services.bakong.token'));
            $result = $bakong->checkTransactionByMD5($payment->transaction_id);

            // Standardize response structure
            $res = json_decode(json_encode($result), true);

            Log::info('Bakong Raw Response', ['result' => $res]);

            $responseCode = null;

            // Extract response code
            if (isset($res['result']['responseCode'])) {
                $responseCode = $res['result']['responseCode'];
            } elseif (isset($res['responseCode'])) {
                $responseCode = $res['responseCode'];
            } elseif (isset($res['status']['code'])) {
                $responseCode = $res['status']['code'];
            }
            // start checking the transaction data amount and currency
            $transactionData = $res['result']['data'] ?? $res['data'] ?? null;

            if ($responseCode === 0 && !empty($transactionData)) {

                $paidAmount = (float) ($transactionData['amount'] ?? 0);
                $paidCurrency = strtoupper($transactionData['currency'] ?? '');
                $expectedAmount = (float) $order->total_price;
                $expectedCurrency = 'USD'; // Ensure this matches your order currency

                // Validate both amount and currency
                if (abs($paidAmount - $expectedAmount) > 0.001 || $paidCurrency !== $expectedCurrency) {
                    Log::warning('Bakong Payment Mismatch Detected', [
                        'order_id' => $order->id,
                        'expected_amount' => $expectedAmount,
                        'paid_amount' => $paidAmount,
                        'expected_curr' => $expectedCurrency,
                        'paid_curr' => $paidCurrency,
                    ]);

                    return 'pending';
                }

                // Proceed with database update if validation succeeds
                DB::transaction(function () use ($payment, $order) {
                    $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();
                    $order = Order::where('id', $order->id)->lockForUpdate()->first();

                    // Check status to ensure this code runs EXACTLY ONCE upon successful payment
                    if ($payment->status !== 'paid') {

                        // Update Payment & Order Status
                        $payment->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);

                        $order->update([
                            'status' => 'confirmed',
                            'payment_transaction_id' => $payment->transaction_id,
                        ]);
                        // Send Customer Email & Notification
                        if ($order->user) {
                            if ($order->user->email) {
                                Mail::to($order->user->email)->send(new OrderPlaced($order));
                                Mail::to(config('mail.admin_email'))->send(new AdminOrderPlaced($order));
                            }

                            Notification::create([
                                'user_id' => $order->user_id,
                                'title' => 'Order Confirmed',
                                'message' => "Your order #{$order->order_number} has been placed successfully.",
                                'type' => 'order_confirmed',
                                'icon' => 'shopping-bag',
                                'url' => "/account/orders/{$order->id}",
                            ]);
                        }

                        Log::info('Order paid and notifications dispatched successfully.', [
                            'order_id' => $order->id,
                        ]);
                    }
                });

                Log::info('Payment validated and marked as PAID', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                    'amount' => $paidAmount,
                ]);

                return 'paid';
            }
            // end checking the transaction data amount and currency

        } catch (\Throwable $e) {
            Log::error('Bakong Exception', [
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
        }

        // Check expiration if not paid
        if ($payment->khqr_expires_at && now()->isAfter($payment->khqr_expires_at)) {
            $this->cancelOrderAndRestoreStock($order, $payment, 'Payment expired');
            return 'expired';
        }

        return 'pending';
    }
    // cancel order and restore stock
    private function cancelOrderAndRestoreStock(Order $order, Payment $payment, string $reason)
    {
        DB::transaction(function () use ($order, $payment, $reason) {
            $payment = Payment::where('id', $payment->id)->lockForUpdate()->first();
            $order = Order::where('id', $order->id)->lockForUpdate()->first();

            // Only process if status is still pending
            if ($payment && $payment->status === 'pending') {
                $payment->update(['status' => 'expired']);

                $order->update([
                    'status' => Order::STATUS_CANCELLED ?? 'cancelled',
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

}