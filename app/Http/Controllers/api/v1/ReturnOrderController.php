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
    // public function updateStatus(Request $request, $id)
    // {
    //     $request->validate([
    //         'status' => 'required|in:approved,rejected'
    //     ]);

    //     $returnOrder = ReturnOrder::findOrFail($id);

    //     $returnOrder->update([
    //         'status' => $request->status

    //     ]);
    //     if ($returnOrder->user && $returnOrder->user->email) {
    //         Mail::to($returnOrder->user->email)->send(new ReturnStatusUpdatedMail($returnOrder));
    //     }
    //     return response()->json([
    //         'message' => 'Return status updated successfully',
    //         'data' => $returnOrder
    //     ]);
    // }

}
