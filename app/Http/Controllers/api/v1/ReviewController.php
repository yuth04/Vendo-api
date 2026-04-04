<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use ImageKit\ImageKit;

class ReviewController extends Controller
{
    /**
     * Get all reviews (optional filter by product)
     */
    public function index(Request $request)
    {
        $query = Review::with(['user', 'product']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $reviews = $query->latest()->get();

        return response()->json([
            'message' => 'Reviews retrieved successfully',
            'data' => $reviews
        ]);
    }

    /**
     * Store review (any logged-in user)
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'image'=>'nullable|image|max:5120'
        ]);
        
        $user = $request->user();
        $imageUrl = null;
        $imageFileId = null;

        if ($request->hasFile('image')) {

            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/review-images',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }
        // Prevent duplicate review per product
        $exists = Review::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'You already reviewed this product'
            ], 400);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => true,
            'image'=>$imageUrl,
            'image_file_id'=>$imageFileId
        ]);

        return response()->json([
            'message' => 'Review submitted successfully',
            'data' => $review
        ], 201);
    }

    /**
     * Update review (only owner)
     */
    public function update(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        // Only owner can delete
        if ($request->user()->id !== $review->user_id && $request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'You cannot delete someone else\'s review.'
            ], 403);
        }

        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'image'=>'nullable|image|max:255'
        ]);
        $imageUrl = $review->image;
        $imageFileId = $review->image_file_id;

        if ($request->hasFile('image')) {

            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/review-images',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            // DELETE OLD IMAGE (safe)
            if ($review->image_file_id) {
                try {
                    $imageKit->deleteFile($review->image_file_id);
                } catch (\Exception $e) {
                    // ignore if already deleted
                }
            }

            //  SET NEW IMAGE DATA
            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }

        $review->update([
            'rating'=>$request->rating,
            'comment'=>$request->comment,
            'image'=>$imageUrl,
            'image_file_id'=>$imageFileId,
        ]);

        return response()->json([
            'message' => 'Review updated successfully',
            'data' => $review
        ]);
    }

    /**
     * Delete review (only owner)
     */
    public function destroy(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        // Only owner can delete
        if ($request->user()->id !== $review->user_id && $request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'You cannot delete someone else\'s review.'
            ], 403);
        }

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }

    /**
     * Product rating summary
     */
    public function productSummary($productId)
    {
        $reviews = Review::where('product_id', $productId);

        return response()->json([
            'average_rating' => round($reviews->avg('rating'), 1),
            'total_reviews' => $reviews->count()
        ]);
    }
}