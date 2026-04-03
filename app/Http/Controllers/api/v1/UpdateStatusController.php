<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmedMail;
<<<<<<< HEAD
use App\Mail\OrderDeliveredMail;
=======
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
use App\Mail\OrderShippedMail;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
class UpdateStatusController extends Controller
{
<<<<<<< HEAD
    private function calculateShipping($province)
    {
        $province = strtolower($province);
        if ($province === 'phnom penh' || $province === 'ភ្នំពេញ' || $province === 'pp') {
            return 1.5;
        }
        return 2.0;
    }
=======
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
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
<<<<<<< HEAD
        $shipping = $this->calculateShipping($order->address->province ?? '');
        Mail::to($order->user->email)->send(new OrderConfirmedMail($order, $shipping));
=======
        Mail::to($order->user->email)->send(new OrderConfirmedMail($order));
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
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
<<<<<<< HEAD
            'shipped_at' => now()->format('h:i d-m-Y '),
=======
            'shipped_at' => now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i'),
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
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
<<<<<<< HEAD
            'delivered_at' => now()->format('h:i d-m-Y'),
=======
            'delivered_at' => now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i'),
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
        ]);
        if ($order->status === 'delivered') {
            $payment = $order->payment;
            if ($payment) {
                $payment->update([
                    'status' => 'paid',
<<<<<<< HEAD
                    'paid_at' => now()->format('h:i d-m-Y'),
=======
                    'paid_at' => now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i'),
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
                    'transaction_id' => 'COD-' . $order->order_number,
                ]);
            }
        }
<<<<<<< HEAD
        $shipping = $this->calculateShipping($order->address->province ?? '');
        $order->load(['items', 'user']);
        Mail::to($order->user->email)->send(new OrderDeliveredMail($order, $shipping));
=======
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
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
<<<<<<< HEAD
            'returned_at' => now()->format('h:i d-m-Y'),
=======
            'returned_at' => now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i'),
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
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
<<<<<<< HEAD
            'cancelled_at' => now()->format('h:i d-m-Y'),
=======
            'cancelled_at' => now()->setTimezone('Asia/Phnom_Penh')->format('Y-m-d h:i'),
>>>>>>> d8445566977b7d73bf2ff501c5c4de213759c59c
        ]);
        return response()->json([
            'message' => 'Order marked as cancelled successfully',
            'order' => $order
        ]);
    }
}
