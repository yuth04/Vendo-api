<?php

namespace App\Http\Controllers\api\v1\Payment;

use App\Http\Controllers\Controller;
use App\Services\BakongService;
use Illuminate\Http\Request;
use App\Mail\AdminOrderPlaced;
use App\Mail\OrderPlaced;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use KHQR\BakongKHQR;
class PaymentController extends Controller
{
    public function checkStatus(Request $request, BakongService $bakongService)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);
        // $user = $request->user();
        $order = Order::find($request->order_id);

        $status = $bakongService->checkPaymentStatus($order);

        Log::info('Returning payment status', [
            'status' => $status,
        ]);

        return response()->json([
            'status' => 'success',
            'payment_status' => $status,

        ]);
    }
    public function handle(Request $request)
    {
        Log::info('Bakong KHQR Callback Received', $request->all());

        $orderNumber = $request->input('order_number');
        $md5Hash = $request->input('md5');

        if (!$orderNumber) {
            return response()->json([
                'message' => 'Order number missing'
            ], 400);
        }


        $order = Order::where('order_number', $orderNumber)
            ->first();


        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }


        $payment = Payment::where('order_id', $order->id)
            ->where('payment_method', 'KHQR')
            ->latest()
            ->first();


        if (!$payment) {
            return response()->json([
                'message' => 'KHQR payment not found'
            ], 404);
        }


        // Prevent duplicate callback
        if ($payment->status === 'paid') {

            return response()->json([
                'status' => 'success',
                'message' => 'Payment already processed'
            ]);
        }


        // Check expiration
        if (
            $payment->khqr_expires_at &&
            now()->isAfter($payment->khqr_expires_at)
        ) {

            DB::transaction(function () use ($payment, $order) {

                $payment->update([
                    'status' => 'expired'
                ]);

                $order->update([
                    'status' => 'cancelled'
                ]);

            });


            return response()->json([
                'message' => 'QR expired'
            ], 400);
        }



        // Update payment and order
        DB::transaction(function () use ($payment, $order, $md5Hash) {

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'transaction_id' =>
                    $md5Hash ?? $payment->transaction_id
            ]);


            $order->update([
                'payment_transaction_id' => $md5Hash,
                'status' => 'confirmed'

            ]);

        });



        // Reload order data for email
        $order->load([
            'items.variant.product',
            'user',
            'address',
            'payment'
        ]);



        // Create notification
        Notification::create([

            'user_id' => $order->user_id,

            'title' => 'Payment Successful',

            'message' =>
                "Your order #{$order->order_number} has been confirmed.",

            'type' => 'payment_success',

            'icon' => 'shopping-bag',

            'url' =>
                "/account/orders/{$order->id}"

        ]);



        // Send email
        try {

            Mail::to(config('mail.admin_email'))
                ->send(new AdminOrderPlaced($order));


            Mail::to($order->user->email)
                ->send(new OrderPlaced($order));


        } catch (\Exception $e) {

            Log::error(
                'Mail failed: ' . $e->getMessage()
            );
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Payment processed successfully'

        ]);
    }
}
