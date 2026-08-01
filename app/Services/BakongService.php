<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Carbon\Carbon;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;
use Log;
use Request;

class BakongService
{

    public function generateKhqrPayment(Order $order): array
    {
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
                'expires_at' => Carbon::parse($existing->khqr_expires_at)
                    ->format('d-m-Y g:i:s A'),
                'amount' => $existing->amount,
                'currency' => 'USD',
            ];
        }
        $expiresAt = now()->addMinutes(5);
        $expirationTimestamp = strval($expiresAt->getTimestampMs());

        $individualInfo = IndividualInfo::withOptionalArray(
            config('app.bakong_account_id'),
            config('app.bakong_merchant_name'),
            config('app.bakong_merchant_city'),
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
            'expires_at' => $expiresAt->format('d-m-Y g:i:s A'),
            'amount' => $payment->amount,
            'currency' => 'USD',
        ];
    }

    // Check payment status
    // public function checkStatus(Request $request)
    // {
    //     $request->validate([
    //         'order_id' => 'required|exists:orders,id',
    //     ]);

    //     // Ownership check: make sure this order belongs to the authenticated user.
    //     $order = Order::where('id', $request->order_id)
    //         ->where('user_id', $request->user()->id)
    //         ->first();

    //     if (!$order) {
    //         return response()->json(['message' => 'Order not found'], 404);
    //     }

    //     $payment = Payment::where('order_id', $order->id)
    //         ->where('payment_method', 'KHQR')
    //         ->latest()
    //         ->first();

    //     if (!$payment) {
    //         return response()->json(['message' => 'No KHQR payment found for this order'], 404);
    //     }

    //     // Already paid
    //     if ($payment->status === 'paid') {
    //         return response()->json([
    //             'payment_status' => 'paid',
    //             'paid_at' => $payment->paid_at,
    //         ]);
    //     }

    //     // Expired
    //     if (now()->isAfter($payment->khqr_expires_at)) {
    //         $payment->update(['status' => 'expired']);
    //         return response()->json(['payment_status' => 'expired']);
    //     }

    //     $bakong = new BakongKHQR(config('services.bakong.token'));
    //     $result = $bakong->checkTransactionByMD5($payment->transaction_id);

    //     if (($result['responseCode'] ?? null) === 0 && !empty($result['data'])) {

    //         $payment->update([
    //             'status' => 'paid',
    //             'paid_at' => now(),
    //             'khqr_expires_at' => now(),
    //         ]);

    //         return response()->json([
    //             'payment_status' => 'paid',
    //             'paid_at' => $payment->paid_at,
    //         ]);
    //     }

    //     return response()->json(['payment_status' => 'pending']);
    // }
}