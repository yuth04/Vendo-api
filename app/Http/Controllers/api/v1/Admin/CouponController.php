<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Notification;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
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
                'message' => "Coupon is empty",
            ]);
        }
        return response()->json([
            'message' => "Retrieved coupon successfully",
            'date' => $coupons
        ]);
    }
    public function getById($id)
    {
        $coupons = Coupon::find($id);
        if (!$coupons) {
            return response()->json([
                'message' => "Coupon not found",
            ]);
        }
        return response()->json([
            'message' => "Retrieved coupon successfully",
            'date' => $coupons
        ]);
    }
    public function store(Request $request)
    {
        $this->authorizeAdminOrStaff();

        $request->validate([
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

        // CREATE COUPON
        $coupon = Coupon::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'value' => $request->value,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'used' => 0,
            'min_amount' => $request->min_amount,
            'max_discount' => $request->max_discount,
            'usage_limit' => $request->usage_limit,
            'description' => $request->description,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'status' => $request->status,
            'discount_id' => $request->discount_id,
        ]);

        // NOTIFICATION
        $discount = $coupon->type === 'percent'
            ? $coupon->value . '% off'
            : '$' . number_format($coupon->value, 2) . ' off';

        $message = 'Use code "' . $coupon->code . '" to get ' . $discount;

        if ($coupon->min_amount) {
            $message .= ' on orders over $' . number_format($coupon->min_amount, 2);
        }

        if ($coupon->end_date) {
            $message .= '. Expires ' . $coupon->end_date->format('M d, Y');
        }

        $message .= '.';

        $this->notifyAllUsers('Special Offer Just for You!', $message, 'coupon', 'tag', '/coupons');

        return response()->json([
            'message' => 'Coupon created successfully',
            'coupon' => $coupon,
        ], 201);
    }
    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can update coupons'
            ], 403);
        }
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return response()->json([
                'message' => 'Coupon not found'
            ]);
        }
        $request->validate([
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
            if ($coupon->image_file_id) {
                $imageKit->deleteFile($coupon->image_file_id);
            }
            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }
        $coupon->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'value' => $request->value,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'min_amount' => $request->min_amount,
            'max_discount' => $request->max_discount,
            'usage_limit' => $request->usage_limit,
            'description' => $request->description,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'status' => $request->status,
            'discount_id' => $request->discount_id
        ]);

        return response()->json([
            'message' => 'Coupon created successfully',
            'coupon' => $coupon
        ]);

    }
    // Delete
    public function destroy($id)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can delete coupons'
            ], 403);
        }

        $coupon = Coupon::find($id);
        if (!$coupon) {
            return response()->json([
                'message' => 'Coupon not found'
            ]);
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
                return response()->json([
                    'message' => 'Failed to delete image from ImageKit',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
        $coupon->delete();
        return response()->json([
            'message' => 'Coupon deleted successfully'
        ], 200);
    }

}
