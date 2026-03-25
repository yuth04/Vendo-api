<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Review::with(['user', 'product']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);

        }

        $reviews = $query->get();

        if ($reviews->isEmpty()) {
            return response()->json([
                'message' => 'Review is empty'
            ]);
        }
        return response()->json([
            'message' => 'Reviews retrieved successfully',
            'data' => $reviews
        ]);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);
        $user = $request->user();
        $reviewCheck = Review::where('user_id', $user->id)->where('product_id', $request->product_id);
        if ($reviewCheck->exists()) {
            return response()->json([
                'message' => 'You have already reviewed this product'
            ], 400);
        }
        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => false
        ]);
        return response()->json([
            'message' => 'Review submitted successfully',
            'data' => $review
        ], 201);
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
    public function update(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'is_approved' => 'nullable|boolean',
        ]);

        if ($request->has('rating')) {
            $review->rating = $request->rating;
        }

        if ($request->has('comment')) {
            $review->comment = $request->comment;
        }

        if ($request->has('is_approved')) {
            $review->is_approved = $request->is_approved;
        }

        $review->save();

        return response()->json([
            'message' => 'Review updated successfully',
            'data' => $review
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $review = Review::findOrFail($id);

        $review->delete();
        DB::statement("SELECT setval(pg_get_serial_sequence('reviews', 'id'), coalesce(max(id), 0) + 1, false) FROM reviews;");
        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }
}
