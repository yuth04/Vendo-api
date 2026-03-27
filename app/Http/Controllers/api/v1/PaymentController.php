<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\BakongService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    // ✅ Generate QR for specific order
    public function generateOrderQr($orderId, BakongService $bakongService)
    {
        $order = Order::with('payment')->findOrFail($orderId);

        if (!$order->payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // ❗ Prevent generating QR again if already paid
        if ($order->payment->status === 'success') {
            return response()->json([
                'message' => 'This order is already paid.'
            ], 400);
        }

        try {
            $qrData = $bakongService->generateKhqr($order, $order->payment);

            return response()->json([
                'message' => 'QR code generated successfully',
                'data' => $qrData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate QR',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ✅ Verify payment with Bakong
    public function verifyPayment(Request $request)
    {
        $request->validate([
            'payment_reference' => 'required|string'
        ]);

        $payment = Payment::where('payment_reference', $request->payment_reference)
            ->with('order')
            ->firstOrFail();

        // ❗ Already paid
        if ($payment->status === 'success') {
            return response()->json([
                'status' => 'SUCCESS',
                'message' => 'Already paid',
                'order' => $payment->order
            ]);
        }

        $bakong = new BakongService();

        $result = $bakong->checkStatus($payment);

        // ✅ NBC standard response
        if (
            isset($result['responseCode']) &&
            $result['responseCode'] === 0 &&
            $result['data']['status'] === 'SUCCESS'
        ) {
            // ✅ Update payment
            $payment->update([
                'status' => 'success',
                'transaction_id' => $result['data']['hash'] ?? null,
                'paid_at' => now()
            ]);

            // ✅ Update order
            $payment->order->update([
                'status' => 'paid'
            ]);

            return response()->json([
                'status' => 'SUCCESS',
                'order' => $payment->order
            ]);
        }

        return response()->json([
            'status' => 'PENDING',
            'message' => 'Still waiting...'
        ], 202);
    }
}