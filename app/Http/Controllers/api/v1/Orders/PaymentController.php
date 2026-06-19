<?php

namespace App\Http\Controllers\api\v1\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Bakong\KHQR\KHQR;
use Bakong\KHQR\Models\IndividualInfo;

class PaymentController extends Controller
{
    public function generate(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::where('id', $request->id)
            ->where('user_id', $request->user()->id)
            ->findOrFail();

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Order is not payable'
            ], 422);
        }

        try {
            $info = new IndividualInfo();
            $info->bakongAccountId = config('services.khqr.bakong_id');  // e.g. yourname@wing
            $info->merchantName = config('services.khqr.merchant_name');
            $info->merchantCity = config('services.khqr.merchant_city', 'Phnom Penh');
            $info->amount = (float) $order->total_price;
            $info->currency = IndividualInfo::USD; // or KHR
            $info->storeLabel = config('services.khqr.store_label', 'Vendo Store');
            $info->terminalLabel = 'Order #' . $order->order_number;
            $info->billNumber = (string) $order->order_number;


            $khqr = new KHQR();
            $result = $khqr->generateIndividual($info);


            return response()->json([
                'success' => true,
                'qr_string' => $result->data->qr,
                'qr_image' => $result->data->qrImage, // base64 PNG
                'md5' => $result->data->md5,     // used to verify payment
                'order_id' => $order->id,
                'amount' => $order->total_price,
                'currency' => 'USD',
            ]);

        } catch (\Throwable $e) {
            Log::error('KHQR generate error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate QR code.',
            ], 500);
        }

    }
    // verify
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'md5' => 'required|string',
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        try {
            $khqr = new KHQR(config('services.khqr.bakong_token'));
            $result = $khqr->checkTransactionByMd5($request->md5);

            if ($result->data?->hash ?? null) {
                // Payment confirmed — update order
                $order->update([
                    'status' => 'confirmed',
                    'payment_method' => 'khqr',
                    'payment_ref' => $result->data->hash,
                    'paid_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'paid' => true,
                    'message' => 'Payment confirmed.',
                    'order' => $order->fresh(),
                ]);
            }

            return response()->json([
                'success' => true,
                'paid' => false,
                'message' => 'Payment not yet received.',
            ]);

        } catch (\Throwable $e) {
            Log::error('KHQR verify error', ['message' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment.',
            ], 500);
        }
    }
}