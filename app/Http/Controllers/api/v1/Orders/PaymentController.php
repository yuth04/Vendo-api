<?php

namespace App\Http\Controllers\api\v1\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Carbon\Carbon;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;
class PaymentController extends Controller
{
    // Generate KHQR
    public function generate(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::findOrFail($request->order_id);

        // Return existing pending payment if still valid
        $existing = Payment::where('order_id', $order->id)
            ->where('payment_method', 'khqr')
            ->where('status', 'pending')
            ->where('khqr_expires_at', '>', now())
            ->first();

        if ($existing) {
            return response()->json([
                'qr_string' => $existing->payment_reference,
                'md5' => $existing->transaction_id,
                'expires_at' => $existing->khqr_expires_at,
                'amount' => $existing->amount,
                'currency' => 'USD',
            ]);
        }

        $expirationTimestamp = strval(floor(microtime(true) * 1000) + 10 * 60 * 1000);

        // Correct way using withOptionalArray
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
            return response()->json(['error' => 'Failed to generate QR code'], 500);
        }

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'khqr',
            'amount' => $order->total_price,
            'transaction_id' => $result->data['md5'],
            'payment_reference' => $result->data['qr'],
            'status' => 'pending',
            'khqr_expires_at' => now()->addMinutes(10),
        ]);

        return response()->json([
            'qr_string' => $payment->payment_reference,
            'md5' => $payment->transaction_id,
            'expires_at' => Carbon::parse($payment->khqr_expires_at)
                ->format('d-m-Y g:i:s A'),
            'amount' => $payment->amount,
            'currency' => 'USD',
        ]);
    }
    // Check payment status
    public function checkStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $payment = Payment::where('order_id', $request->order_id)
            ->where('payment_method', 'khqr')
            ->latest()
            ->firstOrFail();

        // Already paid
        if ($payment->status === 'paid') {
            return response()->json([
                'payment_status' => 'paid',
                'paid_at' => $payment->paid_at,
            ]);
        }

        // Expired
        if (now()->isAfter($payment->khqr_expires_at)) {
            $payment->update(['status' => 'expired']);
            return response()->json(['payment_status' => 'expired']);
        }

        // Ask Bakong API
        $bakong = new BakongKHQR(env('BAKONG_TOKEN'));
        $result = $bakong->checkTransactionByMD5($payment->transaction_id);

        if ($result->status['code'] === 0 && !empty($result->data)) {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            return response()->json([
                'payment_status' => 'paid',
                'paid_at' => $payment->paid_at,
            ]);
        }

        return response()->json(['payment_status' => 'pending']);
    }

}