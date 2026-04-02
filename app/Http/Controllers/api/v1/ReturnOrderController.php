<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Mail\ReturnOrderRequestMail;
use App\Mail\ReturnStatusUpdatedMail;
use App\Models\Order;
use App\Models\ReturnImage;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
class ReturnOrderController extends Controller
{
    public function index()
    {
        $returns = ReturnOrder::with('items.orderItem.product', 'images')->where('user_id', Auth::id())->get();

        return response()->json([
            'message'=> 'Return orders retrieved successfully',
            'data' => $returns
        ]);
    }
    public function show($id)
    {
        $return = ReturnOrder::with('items.orderItem.product', 'images')->where('user_id', Auth::id())->findOrFail($id);

        return response()->json([
            'message'=> 'Return order retrieved successfully',
            'data' => $return
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|string',
            'items' => 'required|array',
            'items.*.order_item_id' => 'required|exists:order_items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'images.*' => 'nullable|image|mimes:jpg,png|max:2048'
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Only delivered orders
        if ($order->status !== 'delivered') {
            return response()->json([
                'message' => 'Only delivered orders can be returned.'
            ], 400);
        }

        if (!$order->delivered_at) {
            return response()->json([
                'message' => 'Order delivery date not found.'
            ], 400);
        }

        $deliveryDate = $order->delivered_at;

        // 7 days return window
        if ($deliveryDate->copy()->addDays(7)->isPast()) {
            return response()->json([
                'message' => 'Return request period has expired.'
            ], 400);
        }

        // Prevent duplicate return request
        if (ReturnOrder::where('order_id', $order->id)->exists()) {
            return response()->json([
                'message' => 'Return request already exists for this order.'
            ], 400);
        }
        DB::beginTransaction();
        $returnOrder = ReturnOrder::create([
            'user_id' => Auth::id(),
            'order_id' => $order->id,
            'reason' => $request->reason,
            'note' => $request->note
        ]);
        foreach ($request->items as $item) {
            ReturnItem::create([
                'return_order_id' => $returnOrder->id,
                'order_item_id' => $item['order_item_id'],
                'quantity' => $item['quantity'],
                'reason' => $item['reason'] ?? null
            ]);
        }
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('returns', 'public');

                ReturnImage::create([
                    'return_order_id' => $returnOrder->id,
                    'image_path' => $path
                ]);
            }
        }
        DB::commit();
        $adminEmail = config('mail.admin_email');
        Mail::to($adminEmail)->send(
            new ReturnOrderRequestMail($returnOrder->load('user'))
        );


        return response()->json([
            'message' => 'Return request submitted successfully.'
        ]);
    }
    public function updateStatus(Request $request, $id)
    {
        if (Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $returnOrder = ReturnOrder::findOrFail($id);

        $request->validate([
            'status' => 'required|in:approved,rejected,processing,completed'
        ]);


        $returnOrder->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Status updated',
            'data' => $returnOrder
        ]);
    }
    // Approve
    public function approve($id)
    {
        $return = ReturnOrder::with('items.OrderItem')->findOrFail($id);

        if ($return->status !== 'pending') {
            return response()->json(['message' => 'Return request is not pending'], 400);
        }
        DB::beginTransaction();
        try {
            $refund = 0;
            foreach ($return->items as $item) {
                $refund += $item->quantity * $item->orderItem->price;

            }
            $return->update([
                'message' => 'Your return request has been approved. Refund amount: $' . number_format($refund, 2),
                'status' => 'approved',
                'approved_at' => now('Asia/Phnom Penh')->format('h:i d-m-y'),
                'refund_amount' => $refund
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error approving return request', 'error' => $e->getMessage()], 500);
        }
    }
    // Reject
    public function reject($id)
    {
        $return = ReturnOrder::findOrFail($id);

        if ($return->status !== 'pending') {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $return->update([
            'status' => 'rejected',
            'rejected_at' => now()
        ]);

        return response()->json(['message' => 'Rejected']);
    }
    public function complete($id)
    {
        $return = ReturnOrder::with('items.orderItem.variant')->findOrFail($id);

        if ($return->status !== 'approved') {
            return response()->json(['message' => 'Not approved'], 400);
        }

        DB::beginTransaction();

        try {
            // ✅ Restock
            foreach ($return->items as $item) {
                $variant = $item->orderItem->variant;
                $variant->increment('stock', $item->quantity);
            }

            // ✅ Update return
            $return->update([
                'status' => 'completed',
                'completed_at' => now(),
                'refund_status' => 'processed',

            ]);

            DB::commit();

            return response()->json(['message' => 'Completed']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
