<?php

namespace App\Http\Controllers\api\v1\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Str;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;

class PaymentController extends Controller
{
    // Check payment status
    public function checkStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        // Ownership check
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('payment_method', 'KHQR')
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'No KHQR payment found for this order'], 404);
        }

        // Already paid
        if ($payment->status === 'paid') {
            return response()->json([
                'payment_status' => 'paid',
                'paid_at' => $payment->paid_at,
            ]);
        }

        // Expired check (Ensure $khqr_expires_at is cast to datetime in Payment model)
        if ($payment->khqr_expires_at && now()->isAfter($payment->khqr_expires_at)) {
            $payment->update(['status' => 'expired']);

            // OPTIONAL: Cancel the order if the payment expires
            $order->update(['status' => 'cancelled']);

            return response()->json(['payment_status' => 'expired']);
        }

        // Call Bakong SDK
        $bakong = new BakongKHQR(config('services.bakong.token'));
        $result = $bakong->checkTransactionByMD5($payment->transaction_id);

        if (($result['responseCode'] ?? null) === 0 && !empty($result['data'])) {

            // FIX: Wrap inside a database transaction to ensure both tables update successfully
            \DB::transaction(function () use ($payment, $order) {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);

                
                $order->update([
                    'status' => 'confirmed', 
                ]);
            });

            return response()->json([
                'payment_status' => 'paid',
                'paid_at' => $payment->fresh()->paid_at,
            ]);
        }

        return response()->json(['payment_status' => 'pending']);
    }
}