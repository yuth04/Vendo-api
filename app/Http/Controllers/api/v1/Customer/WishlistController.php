<?php

namespace App\Http\Controllers\api\v1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WishlistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $wishlistItems = Wishlist::with('product')
            ->where('user_id', $user->id)
            ->get();
        if ($wishlistItems->isEmpty()) {
            return response()->json([
                'message' => 'Wishlist is empty'
            ]);
        }
        return response()->json([
            'message' => 'Wishlist retrieved successfullyy',
            'data' => $wishlistItems
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id'
        ]);

        $user = $request->user();
        $productId = $request->product_id;
        $wishlistCheck = Wishlist::where('user_id', $user->id)->where('product_id', $productId);
        //  check wishlist 
        if ($wishlistCheck->exists()) {
            return response()->json([
                'message' => 'Product already in wishlist'
            ], 400);
        }
        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $productId
        ]);

        return response()->json([
            'message' => 'Product added to wishlist',
            'data' => $wishlist
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */


    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $deleted = Wishlist::where('user_id', $user->id)
            ->where('product_id', $id)
            ->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'Wishlist item not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Product removed from wishlist'
        ], 200);
    }
}

