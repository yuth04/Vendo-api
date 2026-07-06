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
    public function selectMethod(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:KHQR,Cash on Delivery,Credit Card',
        ]);

        // Ownership check: make sure this order belongs to the authenticated user.
        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        try {
            $result = DB::transaction(function () use ($order, $request) {
                $order->update([
                    'payment_method' => $request->payment_method,
                ]);

                // Create or update payment record
                $payment = Payment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'payment_method' => $request->payment_method,
                        'amount' => $order->total_price,
                        'status' => 'pending',
                    ]
                );

                // COD
                if ($request->payment_method === 'Cash on Delivery') {
                    $user = auth()->user();
                    $paymentReference =
                        strtoupper(Str::slug($user->first_name, '_')) .
                        '-' .
                        strtoupper(Str::random(20));
                    $payment->update([
                        'payment_method' => 'Cash on Delivery',
                        'transaction_id' => 'COD-' . strtoupper(Str::random(10)),
                        'payment_reference' => $paymentReference,
                        'status' => 'pending',
                    ]);
                    $order->update([
                        'status' => 'pending',
                    ]);
                }

                // KHQR: generate the QR code immediately so the client gets
                // everything it needs in this one response, no follow-up
                // call to /generate required.
                $khqr = null;
                if ($request->payment_method === 'KHQR') {
                    $khqr = $this->generateKhqrPayment($order);
                }

                return ['payment' => $payment, 'khqr' => $khqr];
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to select payment method',
                'error' => $e->getMessage(),
            ], 500);
        }

        $payment = $result['payment'];

        $response = [
            'message' => 'Payment method selected',
            'order_id' => $order->id,
            'payment_method' => $payment->fresh()->payment_method,
            'payment_status' => $payment->fresh()->status,
        ];

        // Merge in qr_string, md5, expires_at, amount, currency when KHQR was chosen.
        if ($result['khqr']) {
            $response = array_merge($response, $result['khqr']);
        }

        return response()->json($response);
    }

    public function generate(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        try {
            $khqr = $this->generateKhqrPayment($order);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json($khqr);
    }
    private function generateKhqrPayment(Order $order): array
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
    public function checkStatus(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        // Ownership check: make sure this order belongs to the authenticated user.
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

        // Expired
        if (now()->isAfter($payment->khqr_expires_at)) {
            $payment->update(['status' => 'expired']);
            return response()->json(['payment_status' => 'expired']);
        }

        $bakong = new BakongKHQR(config('services.bakong.token'));
        $result = $bakong->checkTransactionByMD5($payment->transaction_id);

        if (($result['responseCode'] ?? null) === 0 && !empty($result['data'])) {

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'khqr_expires_at' => now(),
            ]);

            return response()->json([
                'payment_status' => 'paid',
                'paid_at' => $payment->paid_at,
            ]);
        }

        return response()->json(['payment_status' => 'pending']);
    }
}