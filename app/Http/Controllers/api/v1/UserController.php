<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('orders.items.variant.product')->get();

        return response()->json([
            'message' => 'Users with orders retrieved successfully',
            'data' => $users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,

                    'orders' => $user->orders->map(function ($order) {
                        return [
                            'id' => $order->id,
                            'total' => $order->total_price,
                            'status'=>$order->status,
                            'payment'=>$order->payment->status,

                            'items' => $order->items->map(function ($item) {
                                return [
                                    'quantity' => $item->quantity,
                                    'price' => $item->price,
                                    'product_name' => optional($item->variant->product)->productName,
                                ];
                            })
                        ];
                    })
                ];
            })
        ]);
    }

}
