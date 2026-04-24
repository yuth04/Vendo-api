<?php

namespace App\Http\Controllers\api\v1\Orders;

use App\Http\Controllers\Controller;
use App\Mail\AdminReturnRequestMail;
use App\Mail\ReturnCompletedMail;
use App\Mail\ReturnOrderRequestMail;
use App\Mail\ReturnStatusUpdatedMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnImage;
use App\Models\ReturnItem;
use App\Models\ReturnOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use ImageKit\ImageKit;
class ReturnOrderController extends Controller
{
    // view all return (Admin)
    public function getAll()
    {
        $returns = ReturnOrder::with([
            'items.orderItem'
          
        ])->get();

        return response()->json([
            'message' => 'Retrieved return successfully',
            'data' => $returns
        ]);
    }
    // public function index()
    // {
    //     $returns = ReturnOrder::with('items.orderItem', 'images')->where('user_id', Auth::id())->get();
    //     if ($returns->isEmpty()) {
    //         return response()->json([
    //             'message' => 'No return orders found for this user.',
    //         ]);
    //     }
    //     return response()->json([
    //         'message' => 'Return orders retrieved successfully',
    //         'data' => $returns
    //     ]);
    // }

    public function index()
    {
        $returns = ReturnOrder::with([
            'items.orderItem.productVariant.product'
        ])
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        $data = $returns->map(function ($return) {
            return [
                'return_id' => $return->id,
                'order_id' => $return->order_id,
                'order_number' => $return->order->order_number,
                'reason' => $return->reason,
                'note' => $return->note,
                'status' => $return->status,
                'refund_status' => $return->refund_status,
                'type' => $return->type,
                'created_at' => $return->created_at->format('Y-m-d H:i:s'),
                'image'=> $return->image,
                'items' => $return->items->map(function ($item) {
                    return [
                        'return_item_id' => $item->id,
                        'quantity' => $item->quantity,
                        'reason' => $item->reason,

                        'product' => [

                            'name' => $item->orderItem->product_name,
                            'price' => $item->orderItem->price,
                            'size' => $item->orderItem->size,
                            'color' => $item->orderItem->color,
                            'image' => $item->orderItem->product_image,

                        ]
                    ];
                }),

            
            ];
        });

        return response()->json([
            'message' => $data->isEmpty()
                ? 'No return orders found'
                : 'Return orders retrieved successfully',
            'data' => $data
        ]);
    }
    public function show($id)
    {
        $return = ReturnOrder::with('items.orderItem.product', 'images')->where('user_id', Auth::id())->find($id);

        if (!$return) {
            return response()->json([
                'message' => 'Return order not found.'
            ], 404);
        }

        return response()->json([
            'message' => 'Return order retrieved successfully',
            'data' => $return
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'reason' => 'required|string',
            'type' => 'required|in:refund,exchange',
            'note' => 'nullable|string',
            'order_item_id' => 'required|exists:order_items,id',
            'quantity' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpg,png|max:2048'
        ]);

        $order = Order::where('id', $request->order_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found for this user'
            ], 404);
        }

        if ($order->status !== 'completed') {
            return response()->json([
                'message' => 'Only completed orders can be returned.'
            ], 400);
        }

        if (!$order->completed_at) {
            return response()->json([
                'message' => 'Order completion date not found.'
            ], 400);
        }

        if ($order->completed_at->copy()->addDays(7)->isPast()) {
            return response()->json([
                'message' => 'Return period expired (7 days only).'
            ], 400);
        }

        if (ReturnOrder::where('order_id', $order->id)->exists()) {
            return response()->json([
                'message' => 'Return request already exists for this order.'
            ], 400);
        }

        $orderItem = OrderItem::where('id', $request->order_item_id)
            ->where('order_id', $order->id)
            ->first();

        if (!$orderItem) {
            return response()->json([
                'message' => 'Invalid order item.'
            ], 400);
        }

        // ✅ Prevent over-return
        if ($request->quantity > $orderItem->quantity) {
            return response()->json([
                'message' => 'Return quantity exceeds purchased quantity.'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $imageUrl = null;

            // ✅ Upload AFTER validation
            if ($request->hasFile('image')) {
                $imageKit = new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                );

                $upload = $imageKit->upload([
                    'file' => fopen($request->file('image')->getRealPath(), "r"),
                    'fileName' => $request->file('image')->getClientOriginalName(),
                    'folder' => '/return-images',
                ]);

                if (isset($upload->error)) {
                    return response()->json([
                        'message' => 'Upload failed',
                        'error' => $upload->error->message,
                    ], 400);
                }

                $imageUrl = $upload->result->url;
            }

            $returnOrder = ReturnOrder::create([
                'user_id' => Auth::id(),
                'order_id' => $order->id,
                'type' => $request->type,
                'reason' => $request->reason,
                'note' => $request->note,
                'status' => 'requested',
                'image' => $imageUrl
            ]);

            ReturnItem::create([
                'return_order_id' => $returnOrder->id,
                'order_item_id' => $request->order_item_id,
                'quantity' => $request->quantity
            ]);

            DB::commit();

            $adminEmail = config('mail.admin_email');

            Mail::to($adminEmail)->send(
                new AdminReturnRequestMail($returnOrder->load('user'))
            );

            Mail::to($order->user->email)->send(
                new ReturnOrderRequestMail($returnOrder)
            );

            return response()->json([
                'message' => 'Return request submitted successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Approve
    public function approve($id)
    {
        $return = ReturnOrder::with('items.orderItem')->findOrFail($id);

        if ($return->status !== 'requested') {
            return response()->json([
                'message' => 'Return request is not requested'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $refund = 0;

            foreach ($return->items as $item) {

                $orderItem = $item->orderItem;

                if (!$orderItem) {
                    continue;
                }

                // safer calculation
                $refund += $item->quantity * $orderItem->price;
            }

            $return->update([
                'status' => 'approved',
                'approved_at' => now(),
                'refund_amount' => $refund
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Return approved successfully',
                'refund_amount' => $refund,
                'data' => $return
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Error approving return request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Reject
    public function reject($id)
    {
        $return = ReturnOrder::findOrFail($id);

        if ($return->status !== 'requested') {
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

        if ($return->status === 'completed') {
            return response()->json(['message' => 'Already completed'], 400);
        }

        DB::beginTransaction();

        try {
            foreach ($return->items as $item) {

                $variant = optional($item->orderItem)->variant;

                if (!$variant) {
                    continue; //
                }

                // ✅ restock safely
                $variant->increment('stock', $item->quantity);
            }

            $return->update([
                'status' => 'completed',
                'completed_at' => now(),
                'refund_status' => 'processed',
            ]);

            DB::commit();
            if ($return->user && $return->user->email) {
                Mail::to($return->user->email)->send(new ReturnCompletedMail($return));
            }
            return response()->json([
                'message' => 'Return completed successfully',
                'data' => $return
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to complete return',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // 

}
