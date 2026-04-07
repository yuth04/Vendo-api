<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ImageKit\ImageKit;

class ProductVariantController extends Controller
{
    protected $imageKit;

    public function __construct()
    {
        $this->imageKit = new ImageKit(
            config('imagekit.public_key'),
            config('imagekit.private_key'),
            config('imagekit.url_endpoint')
        );
    }
    public function index()
    {
        $productVariants = ProductVariant::with('product', 'images')->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => 'Product Variants retrieved successfully',
            'product_variant' => $productVariants
        ]);
    }
    public function show($id)
    {
        $productVariant = ProductVariant::with('product', 'images')->find($id);
        if (!$productVariant) {
            return response()->json(['message' => 'Product Variant not found'], 404);
        }
        return response()->json([
            'message' => 'Product Variant retrieved successfully',
            'data' => $productVariant
        ]);
    }
    // store
    // public function store(Request $request)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $validated = $request->validate(
    //             [
    //                 'product_id' => 'required|exists:products,id',
    //                 'size' => 'required|string|max:50',
    //                 'color' => 'required|string|max:50',
    //                 'stock' => 'required|integer|min:0',
    //                 'images' => 'nullable|array|max:4',
    //                 'images.*' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
    //             ]

    //         );

    //         $variant = ProductVariant::create([
    //             'product_id' => $validated['product_id'],
    //             'size' => $validated['size'],
    //             'color' => $validated['color'],
    //             'stock' => $validated['stock'],

    //         ]);

    //         if ($request->hasFile('images')) {

    //             if (count($request->file('images')) > 4) {
    //                 return response()->json([
    //                     'message' => 'Maximum 4 images allowed'
    //                 ], 422);
    //             }

    //             $index = 0;

    //             foreach ($request->file('images') as $image) {

    //                 $upload = $this->imageKit->upload([
    //                     'file' => fopen($image->getPathname(), 'r'),
    //                     'fileName' => time() . '_' . $image->getClientOriginalName(),
    //                     'folder' => '/variant-images/'
    //                 ]);

    //                 ProductImage::create([
    //                     'variant_id' => $variant->id,
    //                     'image' => $upload->result->url,
    //                     'image_file_id' => $upload->result->fileId ?? null,
    //                     'position' => $index,
    //                     'is_primary' => $index === 0
    //                 ]);

    //                 $index++;
    //             }
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Product Variant created successfully',
    //             'data' => $variant->load('images')
    //         ], 201);

    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'message' => 'Failed to create Product Variant',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'size' => 'required|string|max:50',
                'color' => 'required|string|max:50',
                'stock' => 'required|integer|min:0',
                'images' => 'nullable|array|max:4',
                'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
            ]);

            $variant = ProductVariant::create([
                'product_id' => $validated['product_id'],
                'size' => $validated['size'],
                'color' => $validated['color'],
                'stock' => $validated['stock'],
            ]);

            // ✅ Multiple image upload
            if ($request->hasFile('images')) {

                $index = 0;

                foreach ($request->file('images') as $image) {

                    $upload = $this->imageKit->upload([
                        'file' => fopen($image->getPathname(), 'r'),
                        'fileName' => time() . '_' . $image->getClientOriginalName(),
                        'folder' => '/variant-images/',
                    ]);

                    if (isset($upload->error)) {
                        throw new \Exception($upload->error->message);
                    }

                    ProductImage::create([
                        'variant_id' => $variant->id,
                        'image' => $upload->result->url,
                        'image_file_id' => $upload->result->fileId ?? null,
                        'position' => $index,
                        'is_primary' => $index === 0
                    ]);

                    $index++;
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Product Variant created successfully',
                'data' => $variant->load('images')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create Product Variant',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // update

    public function update(Request $request, $id)
    {
        $variant = ProductVariant::with('images')->find($id);

        if (!$variant) {
            return response()->json(['message' => 'Product Variant not found'], 404);
        }

        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'product_id' => 'nullable|exists:products,id',
                'size' => 'nullable|string|max:50',
                'color' => 'nullable|string|max:50',
                'stock' => 'nullable|integer|min:0',
                'images' => 'nullable|array|max:4',
                'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
            ]);

            // 1. Update basic variant fields
            $variant->update(array_filter([
                'product_id' => $validated['product_id'] ?? null,
                'size' => $validated['size'] ?? null,
                'color' => $validated['color'] ?? null,
                'stock' => $validated['stock'] ?? null,
            ]));

            // 2. Handle Image Replacement
            if ($request->hasFile('images')) {

                // Delete existing images from ImageKit and Database
                foreach ($variant->images as $oldImage) {
                    if ($oldImage->image_file_id) {
                        try {
                            $this->imageKit->deleteFile($oldImage->image_file_id);
                        } catch (\Exception $e) {
                            // Log error but continue so the DB stays clean
                            \Log::error("ImageKit Delete Failed: " . $e->getMessage());
                        }
                    }
                    $oldImage->delete();
                }
                // Upload new images
                foreach ($request->file('images') as $index => $image) {
                    $fileName = time() . '_' . $image->getClientOriginalName();

                    $upload = $this->imageKit->upload([
                        'file' => fopen($image->getPathname(), 'r'),
                        'fileName' => $fileName,
                        'folder' => '/variant-images/'
                    ]);

                    if (isset($upload->error)) {
                        throw new \Exception($upload->error->message);
                    }

                    // Create new record
                    $variant->images()->create([
                        'image' => $upload->result->url,
                        'image_file_id' => $upload->result->fileId ?? null,
                        'position' => $index,
                        'is_primary' => $index === 0
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Product Variant updated successfully',
                'data' => $variant->load('images')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update Product Variant',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // delete
    // public function destroy($id)
    // {
    //     $productVariant = ProductVariant::find($id);
    //     if (!$productVariant) {
    //         return response()->json(['message' => 'Product Variant not found'], 404);
    //     }
    //     $productVariant->delete();
    //     return response()->json(['message' => 'Product Variant deleted successfully']);
    // }
    public function destroy($id){
        $variant = ProductVariant::with('images')->find($id);

        if (!$variant) {
            return response()->json([
                'message' => 'Product Variant not found'
            ], 404);
        }

        DB::beginTransaction();

        try {
            // ✅ Delete all images (ImageKit + DB)
            foreach ($variant->images as $image) {

                if ($image->image_file_id) {
                    $this->imageKit->deleteFile($image->image_file_id);
                }

                $image->delete();
            }

            // ✅ Delete variant
            $variant->delete();

            DB::commit();

            return response()->json([
                'message' => 'Product Variant and its images deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete Product Variant',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}