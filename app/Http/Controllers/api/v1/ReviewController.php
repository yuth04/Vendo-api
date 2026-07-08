<?php

namespace App\Http\Controllers\api\v1;

use App\Events\ReviewReplied;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Review;
use Illuminate\Http\Request;
use ImageKit\ImageKit;
use App\Traits\NotifiesUsers;
class ReviewController extends Controller
{
    use NotifiesUsers;
    private function initializeReviewNotification($user, $review, $reply)
    {
        return Notification::create([
            'user_id' => $user->id,
            'title' => 'Your review received a reply',
            'message' => 'replied: ' . $reply->comment,
            'type' => 'review',
            'icon' => 'chat',
            'url' => "/product-details/{$review->product_id}?reply_id={$reply->id}",
            'is_read' => false,
        ]);
    }
    public function index(Request $request)
    {
        $query = Review::with(['user', 'product', 'replies.user'])->whereNull('parent_id')->where('is_approved', true)
        ;

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $reviews = $query->latest()->get();

        $review = $reviews->map(function ($review) {
            return [
                'id' => $review->id,
                'user_id' => $review->user_id,
                'product_id' => $review->product_id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'is_approved' => $review->is_approved,
                'image' => $review->image,
                'created_at' => $review->created_at->format('d-m-Y h:i:s A'),
                'created_at_human' => $review->created_at?->diffForHumans(),
                'parent_id' => $review->parent_id,
                'user' => [
                    'id' => $review->user->id,
                    'first_name' => $review->user->first_name,
                    'last_name' => $review->user->last_name,
                    'email' => $review->user->email,
                    'image' => $review->user->image,
                    'created_at' => $review->user->created_at->format('d-m-Y h:i:s A'),
                    'role' => $review->user->role,
                    'status' => $review->user->status,
                ],
                'product' => [
                    'id' => $review->product->id,
                    'name' => $review->product->productName,
                    'slug' => $review->product->slug,
                    'price' => $review->product->price,
                    'description' => $review->product->description,
                    'image' => $review->product->image,
                    'discount_price' => $review->product->discount_price,
                    'created_at' => $review->product->created_at->format('d-m-Y h:i:s A'),
                ],
                'replies' => $review->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'user_id' => $reply->user_id,
                        'product_id' => $reply->product_id,
                        'rating' => $reply->rating,
                        'comment' => $reply->comment,
                        'is_approved' => $reply->is_approved,
                        'image' => $reply->image,
                        'created_at' => $reply->created_at->format('d-m-Y h:i:s A'),
                        'created_at_human' => $reply->created_at?->diffForHumans(),
                        'parent_id' => $reply->parent_id,
                        'user' => [
                            'id' => $reply->user->id,
                            'first_name' => $reply->user->first_name,
                            'last_name' => $reply->user->last_name,
                            'email' => $reply->user->email,
                            'image' => $reply->user->image,
                            'role' => $reply->user->role,
                            'status' => $reply->user->status,
                            'created_at' => $reply->user->created_at->format('d-m-Y h:i:s A'),
                        ],
                    ];
                }),
            ];
        });

        return response()->json([
            'message' => 'Reviews retrieved successfully',
            'data' => $review
        ]);
    }
    //  Store review (any logged-in user)
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'image' => 'nullable|image|max:5120'
        ]);

        $user = $request->user();

        // ✅ ONLY top-level review check
        $exists = Review::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->whereNull('parent_id')
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'You already reviewed this product'
            ], 400);
        }

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

        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $request->product_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => true,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'parent_id' => null,
        ]);

        return response()->json([
            'message' => 'Review submitted successfully',
            'data' => $review
        ], 201);
    }
    //  Update review (only owner)
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
    //  Delete review (only owner)
    public function destroy(Request $request, $id)
    {
        $review = Review::findOrFail($id);
        $user = $request->user();

        // Authorization: only owner or admin
        if ($user->id !== $review->user_id && $user->role !== 'admin') {
            return response()->json([
                'message' => 'You cannot delete someone else\'s review.'
            ], 403);
        }

        // Delete image from ImageKit (if exists)
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

        //  Delete review
        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }
    // Get product summary (average rating and total reviews)
    public function productSummary($productId)
    {
        $reviews = Review::where('product_id', $productId)
            ->whereNull('parent_id');

        return response()->json([
            'average_rating' => round($reviews->avg('rating'), 1),
            'total_reviews' => $reviews->count()
        ]);
    }
    // Get all reviews (including unapproved) for admin
    public function getAll(Request $request)
    {
        $query = Review::with(['user', 'replies.user', 'product'])
            ->whereNull('parent_id');

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $reviews = $query->latest()->get();
        $review = $reviews->map(function ($review) {
            return [
                'id' => $review->id,
                'user_id' => $review->user_id,
                'product_id' => $review->product_id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'is_approved' => $review->is_approved,
                'image' => $review->image,
                'created_at' => $review->created_at->format('d-m-Y h:i:s A'),
                'created_at_human' => $review->created_at?->diffForHumans(),
                'parent_id' => $review->parent_id,
                'user' => [
                    'id' => $review->user->id,
                    'first_name' => $review->user->first_name,
                    'last_name' => $review->user->last_name,
                    'email' => $review->user->email,
                    'image' => $review->user->image,
                    'created_at' => $review->user->created_at->format('d-m-Y h:i:s A'),
                    'role' => $review->user->role,
                    'status' => $review->user->status,
                ],
                'product' => [
                    'id' => $review->product->id,
                    'name' => $review->product->productName,
                    'slug' => $review->product->slug,
                    'price' => $review->product->price,
                    'description' => $review->product->description,
                    'image' => $review->product->image,
                    'discount_price' => $review->product->discount_price,
                    'created_at' => $review->product->created_at->format('d-m-Y h:i:s A'),
                ],
                'replies' => $review->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'user_id' => $reply->user_id,
                        'product_id' => $reply->product_id,
                        'rating' => $reply->rating,
                        'comment' => $reply->comment,
                        'is_approved' => $reply->is_approved,
                        'image' => $reply->image,
                        'created_at' => $reply->created_at->format('d-m-Y h:i:s A'),
                        'created_at_human' => $reply->created_at?->diffForHumans(),
                        'parent_id' => $reply->parent_id,
                        'user' => [
                            'id' => $reply->user->id,
                            'first_name' => $reply->user->first_name,
                            'last_name' => $reply->user->last_name,
                            'email' => $reply->user->email,
                            'image' => $reply->user->image,
                            'role' => $reply->user->role,
                            'status' => $reply->user->status,
                            'created_at' => $reply->user->created_at->format('d-m-Y h:i:s A'),
                        ],
                    ];
                }),
            ];
        });
        return response()->json([
            'message' => 'Reviews retrieved successfully',
            'data' => $review
        ]);
    }
    // Get review by ID (including replies)
    public function getById($id)
    {
        $review = Review::with(['user', 'replies.user', 'product'])
            ->whereNull('parent_id')
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

        $reply = Review::create([
            'user_id' => auth()->id(),
            'product_id' => $parent->product_id,
            'comment' => $request->comment,
            'rating' => $parent->rating,
            'parent_id' => $parent->id,
            'is_approved' => 1
        ]);
        broadcast(new ReviewReplied($reply))->toOthers();
        // notify owner
        $this->initializeReviewNotification($parent->user, $parent, $reply);

        return response()->json([
            'message' => 'Reply created',
            'data' => $reply
        ]);
    }

    // approve or unapprove review (only admin)
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
    // delete review and its replies
    public function delete(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        $replies = $review->replies; // a Collection, keep the plural name to avoid confusion

        foreach ($replies as $reply) {
            Notification::where('type', 'review')
                ->where('url', 'like', '%reply_id=' . $reply->id)
                ->delete();
        }

        $review->replies()->delete();

        if ($review->image_file_id) {
            try {
                $imageKit = new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                );

                $imageKit->deleteFile($review->image_file_id);
            } catch (\Exception $e) {
                \Log::warning('Image delete failed: ' . $e->getMessage());
            }
        }

        $review->delete();

        return response()->json([
            'message' => 'Review deleted successfully'
        ]);
    }
    // update reply comment (only owner or admin)
    public function updateReply(Request $request, $id)
    {
        $request->validate([
            'comment' => 'required|string'
        ]);

        $review = Review::findOrFail($id);

        if ($review->parent_id === null) {
            return response()->json([
                'message' => 'This is not a reply'
            ], 400);
        }

        $review->update([
            'comment' => $request->comment
        ]);

        Notification::where('type', 'review')
            ->where('url', 'like', '%reply_id=' . $review->id)
            ->update([
                'message' => 'replied: ' . $review->comment,
            ]);

        return response()->json([
            'message' => 'Reply updated successfully',
            'data' => $review
        ]);
    }

}