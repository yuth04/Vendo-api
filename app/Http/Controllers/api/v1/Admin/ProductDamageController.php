<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductDamage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ImageKit\ImageKit;

class ProductDamageController extends Controller
{
    public function index()
    {
        $damages = ProductDamage::with(['product', 'user'])->get();

        return response()->json([
            'message' => 'Retrieved product damage successfully ',
            'data' => $damages->map(function ($damage) {
                return [
                    'id' => $damage->id,
                    'product' => [
                        'name' => $damage->product->productName ?? 'N/A',
                        'slug' => $damage->product->slug ?? 'N/A',
                        'image' => $damage->product->image ?? null,
                    ],
                    'reason' => $damage->reason,
                    'quantity' => $damage->quantity,
                    'current_stock' => $damage->product->variants->sum('stock'),
                    'status' => $damage->status,
                    'image_proof' => $damage->image,
                    'reported_by' => [
                        'first_name' => $damage->user->first_name ?? 'Unknown',
                        'last_name' => $damage->user->last_name ?? 'Unknown',
                        'date' => $damage->created_at->format('d-m-Y'),
                        'time' => $damage->created_at->format('h-i-s A'),
                    ],
                ];
            }),
        ]);
    }
    public function getById($id)
    {
        $damage = ProductDamage::with([
            'product',
            'user'
        ])->find($id);
        if (!$damage) {
            return response()->json([
                'message' => 'Product damage not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Retrieved product damage successfully',
            'data' => [
                'id' => $damage->id,

                'product' => [
                    'name' => $damage->product->productName ?? 'N/A',
                    'slug' => $damage->product->slug ?? 'N/A',
                    'image' => $damage->product->image ?? null,
                ],

                'reason' => $damage->reason,
                'quantity' => $damage->quantity,

                'current_stock' => $damage->product->variant->stock ?? 0,

                'status' => $damage->status,

                'image_proof' => $damage->image,

                'reported_by' => [
                    'first_name' => $damage->user->first_name ?? 'Unknown',
                    'last_name' => $damage->user->last_name ?? 'Unknown',
                    'date' => $damage->created_at->format('d-m-Y'),
                    'time' => $damage->created_at->format('h:i:s A'),
                ],
            ],
        ]);
    }
    // Store
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can create product damage records'
            ], 403);

        }
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'reason' => 'required|string|max:255',
            'quantity' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5048',
            'status' => 'required|string'

        ]);

        $user = Auth::user();
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
                'folder' => '/product-damages',
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
        $date = ProductDamage::create([
            'product_id' => $request->product_id,
            'user_id' => $user->id,
            'reason' => $request->reason,
            'quantity' => $request->quantity,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Created product damage successfully',
            'data' => $date
        ]);
    }
    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can update product damage records'
            ], 403);

        }
        $damage = ProductDamage::find($id);
        if (!$damage) {
            return response()->json([
                'message' => 'Product damage not found'
            ], 404);
        }
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'reason' => 'required|string:max:255',
            'quantity' => 'required|integer|min:1',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:5048',
            'status' => 'required|string'
        ]);

        $user = Auth::user();
        $imageUrl = $damage->image;
        $imageFileId = $damage->image_file_id;
        if ($request->hasFile('image')) {

            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/product-damages',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }
            if ($damage->image_file_id) {
                $imageKit->deleteFile($damage->image_file_id);
            }
            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }
        $damage->update([
            'product_id' => $request->product_id,
            'user_id' => $user->id,
            'reason' => $request->reason,
            'quantity' => $request->quantity,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Created product damage successfully',
            'data' => $damage
        ]);
    }
    public function destroy($id)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can delete product damage records'
            ], 403);

        }
        $damage = ProductDamage::find($id);

        if (!$damage) {
            return response()->json([
                'message' => 'Product damage not found'
            ], 404);
        }

        // Delete image from ImageKit if exists
        if ($damage->image_file_id) {
            try {
                $imageKit = new ImageKit(
                    config('imagekit.public_key'),
                    config('imagekit.private_key'),
                    config('imagekit.url_endpoint')
                );

                $imageKit->deleteFile($damage->image_file_id);

            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Failed to delete image from ImageKit',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        // Delete record
        $damage->delete();

        return response()->json([
            'message' => 'Product damage deleted successfully'
        ], 200);
    }
}
