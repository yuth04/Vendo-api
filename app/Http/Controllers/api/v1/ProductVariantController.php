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
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'size' => 'required|string|max:50',
                'color' => 'required|string|max:50',
                'stock' => 'required|integer|min:0',
                'status' => 'nullable|string|max:50',
                // ✅ MUST be exactly 4 images
                'images' => 'required|array|size:4',
                'images.*' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            ]);

            $variant = ProductVariant::create([
                'product_id' => $validated['product_id'],
                'size' => $validated['size'],
                'color' => $validated['color'],
                'stock' => $validated['stock'],
                'status' => $validated['status'],
            ]);

            foreach ($request->file('images') as $index => $image) {

                $upload = $this->imageKit->upload([
                    'file' => fopen($image->getPathname(), 'r'),
                    'fileName' => time() . '_' . $image->getClientOriginalName(),
                    'folder' => '/variant-images/',
                ]);

                if (isset($upload->error)) {
                    throw new \Exception($upload->error->message);
                }

                $variant->images()->create([
                    'image' => $upload->result->url,
                    'image_file_id' => $upload->result->fileId ?? null,
                    'position' => $index, // 0,1,2,3
                    'is_primary' => $index === 0
                ]);
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


    // public function store(Request $request)
    // {
    //     DB::beginTransaction();

    //     try {
    //         $validated = $request->validate([
    //             'product_id' => 'required|exists:products,id',
    //             'size' => 'required|string|max:50',
    //             'color' => 'required|string|max:50',
    //             'stock' => 'required|integer|min:0',
    //             'images' => 'nullable|array|max:4',
    //             'images.*' => 'image|mimes:jpg,jpeg,png|max:2048',
    //         ]);

    //         $variant = ProductVariant::create([
    //             'product_id' => $validated['product_id'],
    //             'size' => $validated['size'],
    //             'color' => $validated['color'],
    //             'stock' => $validated['stock'],
    //         ]);

    //         // ✅ Multiple image upload
    //         if ($request->hasFile('images')) {

    //             $index = 0;

    //             foreach ($request->file('images') as $image) {

    //                 $upload = $this->imageKit->upload([
    //                     'file' => fopen($image->getPathname(), 'r'),
    //                     'fileName' => time() . '_' . $image->getClientOriginalName(),
    //                     'folder' => '/variant-images/',
    //                 ]);

    //                 if (isset($upload->error)) {
    //                     throw new \Exception($upload->error->message);
    //                 }

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
    // update



    // update

    
    public function update(Request $request, $id){
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
                'images' => 'nullable|array|size:4',
                'images.*' => 'required|image|mimes:jpg,jpeg,png|max:5120',
            ]);

            // update fields
            $variant->update(array_filter([
                'product_id' => $validated['product_id'] ?? null,
                'size' => $validated['size'] ?? null,
                'color' => $validated['color'] ?? null,
                'stock' => $validated['stock'] ?? null,
            ]));

            //  replace images
            if ($request->hasFile('images')) {

                // delete old images
                foreach ($variant->images as $oldImage) {

                    if ($oldImage->image_file_id) {
                        try {
                            $this->imageKit->deleteFile($oldImage->image_file_id);
                        } catch (\Exception $e) {
                            \Log::error($e->getMessage());
                        }
                    }

                    $oldImage->delete();
                }

                // upload new 4 images
                foreach ($request->file('images') as $index => $image) {

                    $upload = $this->imageKit->upload([
                        'file' => fopen($image->getPathname(), 'r'),
                        'fileName' => time() . '_' . $image->getClientOriginalName(),
                        'folder' => '/variant-images',
                    ]);

                    if (isset($upload->error)) {
                        throw new \Exception($upload->error->message);
                    }

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