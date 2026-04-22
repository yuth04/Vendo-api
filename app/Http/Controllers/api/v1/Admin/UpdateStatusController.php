<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderDeliveredMail;
use App\Mail\OrderShippedMail;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
class UpdateStatusController extends Controller
{

    private function calculateShipping($province)
    {
        $province = strtolower($province);
        if ($province === 'phnom penh' || $province === 'ភ្នំពេញ' || $province === 'pp') {
            return 1.5;
        }
        return 2.0;
    }

    public function markAsConfirmed($id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = Order::findOrFail($id);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Only pending orders can be confirmed'
            ], 400);
        }

        $order->update([
            'status' => 'confirmed'
        ]);

        Mail::to($order->user->email)->send(new OrderConfirmedMail($order));

        return response()->json([
            'message' => 'Order confirmed successfully',
            'order' => $order
        ]);
    }
    // Mark shipping (Admin only)
    public function markAsShipping($id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = Order::findOrFail($id);

        if ($order->status !== 'confirmed') {
            return response()->json([
                'message' => 'Only confirmed orders can be shipped'
            ], 400);
        }

        $order->update([
            'status' => 'shipping',
            'shipped_at' => now(),
        ]);
        Mail::to($order->user->email)->send(new OrderShippedMail($order));
        return response()->json([
            'message' => 'Order marked as shipping successfully',
            'order' => $order
        ]);
    }

    // Mark delivered (Admin only)
    public function markAsDelivered($id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = Order::findOrFail($id);

        if ($order->status !== 'shipping') {
            return response()->json([
                'message' => 'Only shipping orders can be delivered'
            ], 400);
        }

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
        if ($order->status === 'delivered') {
            $payment = $order->payment;
            if ($payment) {
                $payment->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'transaction_id' => 'COD-' . $order->order_number,
                ]);
            }
        }
        $transaction_id = $order->payment->transaction_id;
        $payment_reference = $order->payment->payment_reference;
        $shipping = $this->calculateShipping($order->address->province ?? '');
        $order->load(['items', 'user']);
        Mail::to($order->user->email)->send(new OrderDeliveredMail($order, $shipping, $transaction_id, $payment_reference));

        return response()->json([
            'message' => 'Order delivered successfully',
            'order' => $order
        ]);
    }
    // Return order (Admin only)
    public function markAsReturned($id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $order = Order::findOrFail($id);
        if ($order->status !== 'delivered') {
            return response()->json([
                'message' => 'Only delivered orders can be returned'
            ], 400);
        }
        $order->update([
            'status' => 'returned',
            'returned_at' => now(),

        ]);
        return response()->json([
            'message' => 'Order marked as returned successfully',
            'order' => $order
        ]);
    }
    // Cancel order (Admin only)
    public function markAsCancelled($id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $order = Order::findOrFail($id);
        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'message' => 'Only pending or confirmed orders can be cancelled'
            ], 400);
        }
        $order->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),



        ]);
        return response()->json([
            'message' => 'Order marked as cancelled successfully',
            'order' => $order
        ]);
    }
    // Payment status update (Admin only)
    public function updatePaymentStatus($id, Request $request)
    {
        $request->validate([
            'status' => 'required|in:paid,unpaid,refunded',
        ]);
        if (Auth::user()->role !== "admin") {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $order = Order::findOrFail($id);
        $payment = $order->payment;
        if (!$payment) {
            return response()->json([
                'message' => 'Payment not found'
            ], 404);
        }
        if ($payment->status == $request->status) {
            return response()->json([
                'message' => 'Payment is already marked as ' . $request->status
            ], 400);
        }
        if ($payment->status !== 'paid') {
            return response()->json([
                'message' => 'Only paid payments can be refunded'
            ], 403);
        }
        if ($order->status !== 'delivered') {
            return response()->json([
                'message' => 'Only delivered orders can be marked as paid'
            ], 400);
        }
        $payment->update([
            'status' => $request->status,
            'paid_at' => now(),
            'transaction_id' => 'COD-' . $order->order_number,
        ]);
        return response()->json([
            'message' => 'Payment status updated to ' . $request->status . ' successfully',
            'payment' => $payment
        ]);
    }
}
