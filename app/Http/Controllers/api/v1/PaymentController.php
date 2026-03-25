<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Services\BakongService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
class PaymentController extends Controller
{
    public function generateOrderQr($orderId, BakongService $bakongService)
    {
        $order = Order::findOrFail($orderId);

        // Safety: Don't show QR for orders that are already paid or cancelled
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'This order is already paid.'], 400);
        }

        try {
            $qrData = $bakongService->generateKhqr($order);

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

    public function verifyPayment(Request $request)
    {
        $order = Order::where('order_number', $request->order_number)->firstOrFail();

        if ($order->payment_status === 'paid') {
            return response()->json(['status' => 'SUCCESS', 'message' => 'Paid']);
        }

        $bakong = new BakongService();
        $result = $bakong->checkStatus($order);

        // Standard NBC Response check
        if (isset($result['responseCode']) && $result['responseCode'] === 0) {
            if ($result['data']['status'] === 'SUCCESS') {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'processing',
                    'paid_at' => now(),
                    'hash' => $result['data']['hash'] // Critical for tracking
                ]);

                return response()->json(['status' => 'SUCCESS', 'order' => $order]);
            }
        }

        return response()->json(['status' => 'PENDING', 'message' => 'Still waiting...'], 402);
    }

}
