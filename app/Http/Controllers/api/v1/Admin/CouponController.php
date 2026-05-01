<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use ImageKit\ImageKit;

class CouponController extends Controller
{
    // Coupon
    public function apply(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $cartTotal = $request->cart_total;

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid coupon'], 404);
        }

        if (!$coupon->isValid($cartTotal)) {
            return response()->json(['message' => 'Coupon not valid'], 400);
        }

        $discount = $coupon->calculateDiscount($cartTotal);

        return response()->json([
            'coupon' => $coupon->code,
            'discount' => $discount,
            'final_total' => $cartTotal - $discount
        ]);
    }
    public function index()
    {
        $coupons = Coupon::all();
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
        ]);
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
        $coupon = Coupon::create([
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

        ]);

        return response()->json([
            'message' => 'Coupon created successfully',
            'coupon' => $coupon
        ]);

    }
    public function update(Request $request, $id)
    {
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

        ]);

        return response()->json([
            'message' => 'Coupon created successfully',
            'coupon' => $coupon
        ]);

    }
    // Delete
    public function destroy($id)
    {
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
