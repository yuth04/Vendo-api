<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PayWayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayWayController extends Controller
{
    public function __construct(private PayWayService $payway)
    {
    }

    /**
     * Create a PayWay payment
     * POST /api/v1/payment/payway/create
     */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'order_id'       => 'required|exists:orders,id',
            'payment_option' => 'nullable|string|in:abapay_khqr_deeplink,abapay_khqr,cards,alipay,wechat',
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This order is not payable.',
            ], 422);
        }

        try {
            $transactionId = 'VD' . $order->id . '-' . (time() % 10000000);

            $result = $this->payway->createTransaction(
                transactionId: $transactionId,
                amount:        (float) $order->total_price,
                currency:      'USD',
                paymentOption: $request->input('payment_option', 'abapay_khqr_deeplink'),
            );

            // Save to payments table
            Payment::updateOrCreate(
                ['order_id' => $order->id],
                [
                    'payment_method'    => 'payway',
                    'amount'            => $order->total_price,
                    'transaction_id'    => $transactionId,
                    'payment_reference' => $transactionId,
                    'status'            => 'pending',
                ]
            );

            // Generate QR image URL from deeplink for frontend to display
            $abapayDeeplink = $result['abapay_deeplink'] ?? null;
            $qrImage        = $abapayDeeplink
                ? 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($abapayDeeplink)
                : null;

            return response()->json([
                'success'         => true,
                'tran_id'         => $transactionId,
                'checkout_url'    => $result['checkout_qr_url'] ?? null,
                'qr_string'       => $result['qr_string'] ?? null,
                'abapay_deeplink' => $abapayDeeplink,
                'qr_image'        => $qrImage,
                'amount'          => $order->total_price,
                'currency'        => 'USD',
            ]);

        } catch (\Throwable $e) {
            Log::error('PayWay create error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment. Please try again.',
            ], 500);
        }
    }

    /**
     * Check payment status
     * POST /api/v1/payment/payway/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->with('payment')
            ->firstOrFail();

        if ($order->status === 'confirmed') {
            return response()->json([
                'success' => true,
                'paid'    => true,
                'message' => 'Order already paid.',
            ]);
        }

        if (!$order->payment || !$order->payment->transaction_id) {
            return response()->json([
                'success' => false,
                'paid'    => false,
                'message' => 'No payment initiated for this order.',
            ], 422);
        }

        try {
            $result        = $this->payway->checkTransaction($order->payment->transaction_id);
            $paymentStatus = $result['payment_status'] ?? null;

            if ($paymentStatus === 'APPROVED') {
                $order->update(['status' => 'confirmed']);

                $order->payment->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'paid'    => true,
                    'message' => 'Payment confirmed! Your order is being prepared.',
                ]);
            }

            if (in_array($paymentStatus, ['DECLINED', 'CANCELLED'])) {
                $order->payment->update(['status' => 'failed']);

                return response()->json([
                    'success' => true,
                    'paid'    => false,
                    'message' => 'Payment was declined or cancelled. Please try again.',
                ]);
            }

            return response()->json([
                'success' => true,
                'paid'    => false,
                'message' => 'Waiting for payment...',
            ]);

        } catch (\Throwable $e) {
            Log::error('PayWay verify error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment.',
            ], 500);
        }
    }

    /**
     * Handle PayWay webhook callback
     * POST /api/v1/payment/payway/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        $signature = $request->header('X-Payway-Hmac-Sha512', '');

        if (!$this->payway->verifyWebhook($request->all(), $signature)) {
            Log::warning('PayWay webhook invalid signature', [
                'received' => $signature,
                'data'     => $request->all(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 403);
        }

        $transactionId = $request->input('tran_id');
        $paymentStatus = $request->input('payment_status');

        $payment = Payment::where('transaction_id', $transactionId)
            ->with('order')
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found.'], 404);
        }

        if ($paymentStatus === 'APPROVED' && $payment->status !== 'paid') {
            $payment->update([
                'status'  => 'paid',
                'paid_at' => now(),
            ]);

            $payment->order->update(['status' => 'confirmed']);

            Log::info('PayWay payment confirmed via webhook', [
                'order_id' => $payment->order_id,
                'tran_id'  => $transactionId,
            ]);
        }

        if (in_array($paymentStatus, ['DECLINED', 'CANCELLED']) && $payment->status !== 'paid') {
            $payment->update(['status' => 'failed']);

            Log::info('PayWay payment failed via webhook', [
                'order_id'       => $payment->order_id,
                'tran_id'        => $transactionId,
                'payment_status' => $paymentStatus,
            ]);
        }

        return response()->json(['message' => 'OK']);
    }
}