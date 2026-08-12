<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Mail\AdminOrderPlaced;
use App\Mail\OrderPlaced;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class BakongCallbackController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Bakong KHQR Callback Received: ', $request->all());

        $orderNumber = $request->input('order_number');
        $md5Hash = $request->input('md5');

        if (!$orderNumber) {
            return response()->json(['message' => 'Order number is missing from payload'], 400);
        }

        $order = Order::where('order_number', $orderNumber)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $payment = Payment::where('order_id', $order->id)
            ->where('payment_method', 'KHQR')
            ->latest()
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Associated KHQR payment record not found'], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json(['status' => 'success', 'message' => 'Already processed']);
        }

        if ($payment->khqr_expires_at && now()->isAfter($payment->khqr_expires_at)) {
            DB::transaction(function () use ($payment, $order) {
                $payment->update(['status' => 'expired']);
                $order->update(['status' => 'cancelled']);
            });

            return response()->json(['message' => 'Payment QR expired'], 400);
        }

        DB::transaction(function () use ($payment, $order, $md5Hash) {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'transaction_id' => $md5Hash ?? $payment->transaction_id
            ]);

            $order->update([
                'status' => 'processing',
                'payment_transaction_id' => $md5Hash
            ]);
        });

        $order->load(['items.variant.product', 'user', 'address', 'payment']);

        // Send emails synchronously (no queues)
        try {
            if ($order->user && $order->user->email) {
                Mail::to($order->user->email)->send(new OrderPlaced($order));
            }

            if (config('mail.admin_email')) {
                Mail::to(config('mail.admin_email'))->send(new AdminOrderPlaced($order));
            }
        } catch (\Exception $mailException) {
            Log::error('Bakong Callback Notification Error: ' . $mailException->getMessage());
        }

        return response()->json(['status' => 'success', 'message' => 'Paid successfully']);
    }
}