<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ImageKit\ImageKit;
use App\Traits\NotifiesUsers;

class CouponController extends Controller
{
    use NotifiesUsers;

    private function authorizeAdminOrStaff(): void
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            abort(403, 'Only super-admin and admin can access this.');
        }
    }

    public function index()
    {
        $coupons = Coupon::latest()->get();

        if ($coupons->isEmpty()) {
            return response()->json([
                'message' => 'Coupon is empty',
                'data' => [],
            ]);
        }

        return response()->json([
            'message' => 'Retrieved coupon successfully',
            'data' => $coupons
        ]);
    }

    public function getById($id)
    {
        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'message' => 'Coupon not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Retrieved coupon successfully',
            'data' => $coupon
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdminOrStaff();

        $request->merge(['code' => strtoupper((string) $request->code)]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'min_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5048',
            'status' => 'required|string',
            'discount_id' => 'nullable|exists:discounts,id',
        ]);

        // IMAGE UPLOAD
        $imageUrl = null;
        $imageFileId = null;

        if ($request->hasFile('image')) {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), 'r'),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/coupon-images',
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

        try {
            $coupon = DB::transaction(function () use ($validated, $imageUrl, $imageFileId) {
                return Coupon::create([
                    'name' => $validated['name'],
                    'code' => $validated['code'],
                    'type' => $validated['type'],
                    'value' => $validated['value'],
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                    'used' => 0,
                    'min_amount' => $validated['min_amount'] ?? null,
                    'max_discount' => $validated['max_discount'] ?? null,
                    'usage_limit' => $validated['usage_limit'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'image' => $imageUrl,
                    'image_file_id' => $imageFileId,
                    'status' => $validated['status'],
                    'discount_id' => $validated['discount_id'] ?? null,
                ]);
            });
        } catch (\Exception $e) {

            if ($imageFileId) {
                try {
                    (new ImageKit(
                        config('imagekit.public_key'),
                        config('imagekit.private_key'),
                        config('imagekit.url_endpoint')
                    ))->deleteFile($imageFileId);
                } catch (\Exception $cleanupException) {
                    Log::error('Failed to clean up orphaned coupon image: ' . $cleanupException->getMessage());
                }
            }

            Log::error('Coupon creation failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to create coupon',
                'error' => $e->getMessage(),
            ], 500);
        }


        if ($coupon->status === 'active') {
            $discountStr = $coupon->type === 'percent'
                ? $coupon->value . '% off'
                : '$' . number_format($coupon->value, 2) . ' off';

            $message = 'Use code "' . $coupon->code . '" to get ' . $discountStr;

            if ($coupon->min_amount) {
                $message .= ' on orders over $' . number_format($coupon->min_amount, 2);
            }

            if ($coupon->end_date) {
                $message .= '. Expires ' . $coupon->end_date->format('M d, Y');
            }

            $message .= '.';

            $this->notifyAllUsers('Special Offer Just for You!', $message, 'coupon', 'tag', '/coupons');
        }

        return response()->json([
            'message' => 'Coupon created successfully',
            'coupon' => $coupon,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $this->authorizeAdminOrStaff();

        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'message' => 'Coupon not found'
            ], 404);
        }

        $request->merge(['code' => strtoupper((string) $request->code)]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:coupons,code,' . $coupon->id,
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'min_amount' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5048',
            'status' => 'required|string',
            'discount_id' => 'nullable|exists:discounts,id'
        ]);

        $imageUrl = $coupon->image;
        $imageFileId = $coupon->image_file_id;
        $oldImageFileId = $coupon->image_file_id;

        if ($request->hasFile('image')) {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/coupon-images',
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

        try {
            DB::transaction(function () use ($coupon, $validated, $imageUrl, $imageFileId) {
                $coupon->update([
                    'name' => $validated['name'],
                    'code' => $validated['code'],
                    'type' => $validated['type'],
                    'value' => $validated['value'],
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                    'min_amount' => $validated['min_amount'] ?? null,
                    'max_discount' => $validated['max_discount'] ?? null,
                    'usage_limit' => $validated['usage_limit'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'image' => $imageUrl,
                    'image_file_id' => $imageFileId,
                    'status' => $validated['status'],
                    'discount_id' => $validated['discount_id'] ?? null,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Coupon update failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to update coupon',
                'error' => $e->getMessage(),
            ], 500);
        }

        if ($request->hasFile('image') && $oldImageFileId) {
            try {
                (new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                ))->deleteFile($oldImageFileId);
            } catch (\Exception $e) {
                Log::error('Failed to delete old coupon image: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Coupon updated successfully',
            'coupon' => $coupon
        ]);
    }

    // Delete
    public function destroy($id)
    {
        $this->authorizeAdminOrStaff();

        $coupon = Coupon::find($id);

        if (!$coupon) {
            return response()->json([
                'message' => 'Coupon not found'
            ], 404);
        }

        if ($coupon->image_file_id) {
            try {
                $imageKit = new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                );

                $imageKit->deleteFile($coupon->image_file_id);
            } catch (\Exception $e) {

                Log::error('Failed to delete image from ImageKit for coupon #' . $coupon->id . ': ' . $e->getMessage());
            }
        }

        $coupon->delete();

        return response()->json([
            'message' => 'Coupon deleted successfully'
        ], 200);
    }
}