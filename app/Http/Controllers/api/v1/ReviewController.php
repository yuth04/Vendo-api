<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;
use ImageKit\ImageKit;

class ReviewController extends Controller
{

    public function index(Request $request)
    {
        $query = Review::with(['user', 'product'])->where('is_approved', true);

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
            'image' => 'nullable|image|max:5120'
        ]);

        $user = $request->user();

        // Check duplicate FIRST (important)
        $exists = Review::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'You already reviewed this product'
            ], 400);
        }

        $imageUrl = null;
        $imageFileId = null;

        // Upload image only if exists
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

        //  Create review
        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => true,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId
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
        $user = $request->user();

        //  Authorization: only owner or admin
        if ($user->id !== $review->user_id && $user->role !== 'admin') {
            return response()->json([
                'message' => 'You cannot update someone else\'s review.'
            ], 403);
        }

        // Validation
        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'image' => 'nullable|image|max:5120', // 5MB

        ]);

        $imageUrl = $review->image;
        $imageFileId = $review->image_file_id;

        // Handle Image Upload
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

            //  Delete old image (if exists)
            if ($review->image_file_id) {
                try {
                    $imageKit->deleteFile($review->image_file_id);
                } catch (\Exception $e) {
                    // ignore error
                }
            }

            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }

        // Update only provided fields
        $review->update([
            'rating' => $request->has('rating') ? $request->rating : $review->rating,
            'comment' => $request->has('comment') ? $request->comment : $review->comment,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
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
        $user = $request->user();

        // ✅ Authorization: only owner or admin
        if ($user->id !== $review->user_id && $user->role !== 'admin') {
            return response()->json([
                'message' => 'You cannot delete someone else\'s review.'
            ], 403);
        }

        // ✅ Delete image from ImageKit (if exists)
        if ($review->image_file_id) {
            try {
                $imageKit = new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                );

                $imageKit->deleteFile($review->image_file_id);
            } catch (\Exception $e) {
                // optional: log error instead of breaking
                \Log::warning('Image delete failed: ' . $e->getMessage());
            }
        }

        // ✅ Delete review
        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }


    public function productSummary($productId)
    {
        $reviews = Review::where('product_id', $productId);

        return response()->json([
            'average_rating' => round($reviews->avg('rating'), 1),
            'total_reviews' => $reviews->count()
        ]);
    }
    public function getAll(Request $request)
    {
        $query = Review::with(['user', 'replies.user', 'product'])
            ->whereNull('parent_id');

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $reviews = $query->latest()->get();

        return response()->json([
            'message' => 'Reviews retrieved successfully',
            'data' => $reviews
        ]);
    }
    public function getById($id)
    {
        $review = Review::with(['user', 'replies.user', 'product'])
            ->whereNull('parent_id') // optional but recommended
            ->findOrFail($id);

        return response()->json([
            'message' => 'Review retrieved successfully',
            'data' => $review
        ]);
    }
    public function reply(Request $request, $id)
    {
        $request->validate([
            'comment' => 'required|string'
        ]);

        $parent = Review::findOrFail($id);
        if ($parent->parent_id !== null) {
            return response()->json([
                'message' => 'Cannot reply to a reply'
            ], 400);
        }
        $reply = Review::create([
            'user_id' => auth()->id(),
            'product_id' => $parent->product_id,
            'comment' => $request->comment,
            'rating' => $parent->rating,
            'parent_id' => $parent->id,
            'is_approved' => 1
        ]);

        return response()->json([
            'message' => 'Reply created',
            'data' => $reply
        ]);
    }
    public function updateReply(Request $request, $id)
    {
        $request->validate([
            'comment' => 'required|string'
        ]);

        $review = Review::findOrFail($id);

        //  Check if it's a reply
        if ($review->parent_id === null) {
            return response()->json([
                'message' => 'This is not a reply'
            ], 400);
        }

        // Optional: only allow owner/admin
        if (auth()->id() !== $review->user_id) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $review->update([
            'comment' => $request->comment
        ]);

        return response()->json([
            'message' => 'Reply updated successfully',
            'data' => $review
        ]);
    }
    public function approved(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        $review->is_approved = !$review->is_approved;
        $review->save();

        return response()->json([
            'message' => $review->is_approved ? 'Approved' : 'Unapproved',
            'data' => $review
        ]);
    }
    public function delete($id)
    {
        $review = Review::findOrFail($id);

        // ✅ Check if it's a reply
        if ($review->parent_id !== null) {
            $review->delete();

            return response()->json([
                'message' => 'Reply deleted successfully'
            ]);
        }

        return response()->json([
            'message' => 'This is not a reply'
        ], 400);
    }


}