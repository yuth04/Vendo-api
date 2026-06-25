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
    /**
     * Generate KHQR Payment
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Order is not payable.',
            ], 422);
        }

        try {
            $info = new IndividualInfo();

            $info->bakongAccountId = config('services.khqr.bakong_id');
            $info->merchantName    = config('services.khqr.merchant_name', 'Vendo Store');
            $info->merchantCity    = config('services.khqr.merchant_city', 'Phnom Penh');

            $info->amount          = (float) $order->total_price;
            $info->currency        = IndividualInfo::USD;

            $info->storeLabel      = 'Vendo Store';
            $info->terminalLabel   = 'Order #' . $order->order_number;
            $info->billNumber      = (string) $order->order_number;

            $khqr = new KHQR();

            $result = $khqr->generateIndividual($info);

            if (!isset($result->data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to generate KHQR.',
                ], 500);
            }

            return response()->json([
                'success'   => true,
                'order_id'  => $order->id,
                'amount'    => $order->total_price,
                'currency'  => 'USD',
                'qr_string' => $result->data->qr ?? null,
                'md5'       => $result->data->md5 ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('KHQR Generate Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify KHQR Payment
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => ['required', 'exists:orders,id'],
            'md5'      => ['required', 'string'],
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        if ($order->status === 'confirmed') {
            return response()->json([
                'success' => true,
                'paid'    => true,
                'message' => 'Order already paid.',
                'order'   => $order,
            ]);
        }

        try {
            $khqr = new KHQR(
                config('services.khqr.bakong_token')
            );

            $result = $khqr->checkTransactionByMd5(
                $request->md5
            );

            if (
                isset($result->data) &&
                !empty($result->data->hash)
            ) {
                $order->update([
                    'status'         => 'confirmed',
                    'payment_method' => 'khqr',
                    'payment_ref'    => $result->data->hash,
                    'paid_at'        => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'paid'    => true,
                    'message' => 'Payment confirmed.',
                    'order'   => $order->fresh(),
                ]);
            }

            return response()->json([
                'success' => true,
                'paid'    => false,
                'message' => 'Payment not received yet.',
            ]);
        } catch (\Throwable $e) {
            Log::error('KHQR Verify Error', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}