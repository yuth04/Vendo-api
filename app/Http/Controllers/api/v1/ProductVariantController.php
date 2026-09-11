<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use ImageKit\ImageKit;

class ProductVariantController extends Controller
{
    protected $imageKit;

    public function __construct(protected SystemLogService $systemLogService)
    {
        $this->imageKit = new ImageKit(
            config('imagekit.public_key'),
            config('imagekit.private_key'),
            config('imagekit.url_endpoint')
        );
    }
    private function generateSKU($product, $color, $size)
    {
        $productCode = strtoupper(substr($product->name, 0, 3));
        $colorCode = strtoupper(substr($color, 0, 3));
        $sizeCode = strtoupper($size);

        do {
            $sku = $productCode . '-' . $colorCode . '-' . $sizeCode . '-' . rand(100, 999);
        } while (ProductVariant::where('sku', $sku)->exists());

        return $sku;
    }
    public function index()
    {
        $productVariants = ProductVariant::with('product', 'images')->where('status', 'active')->orderBy('id', 'asc')->get();
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
    public function store(Request $request)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can create product variant'
            ], 403);
        }
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'size' => 'required|string|max:50',
                'color' => 'required|string|max:50',
                'stock' => 'required|integer|min:0',
                'status' => 'nullable|string|max:50',
                'images' => 'nullable|array|min:1|max:4',
                'images.*' => 'image|mimes:jpg,jpeg,png|max:5120',
            ]);
            $product = Product::findOrFail($validated['product_id']);
            $variant = ProductVariant::create([
                'product_id' => $validated['product_id'],
                'sku' => $this->generateSKU($product, $validated['color'], $validated['size']),
                'size' => $validated['size'],
                'color' => $validated['color'],
                'stock' => $validated['stock'],
                'status' => $validated['status'] ?? 'active',
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {

                    $upload = $this->imageKit->upload([
                        'file' => fopen($image->getPathname(), 'r'),
                        'fileName' => time() . '_' . $image->getClientOriginalName(),
                        'folder' => '/variant-images/',
                    ]);

                    if (isset($upload->error)) {
                        throw new \Exception($upload->error->message);
                    }

                    $newImg = $variant->images()->create([
                        'image' => $upload->result->url,
                        'image_file_id' => $upload->result->fileId ?? null,
                        'position' => $index,
                        'is_primary' => $index === 0
                    ]);

                    $uploadedImages[] = $newImg->toArray();
                }
            }
            $newValues = array_merge($variant->toArray(), ['images' => $uploadedImages]);
            $this->systemLogService->log(
                action: 'create',
                module: 'ProductVariant',
                description: "Created product variant: {$variant->sku}",
                entity: $variant,
                oldValues: null,
                newValues: $newValues
            );
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
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can update product variant'
            ], 403);
        }

        $variant = ProductVariant::with('images')->find($id);
        if (!$variant) {
            return response()->json(['message' => 'Product Variant not found'], 404);
        }

        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'product_id' => 'nullable|exists:products,id',
                'sku' => 'nullable|string|max:100|unique:product_variants,sku,' . $id,
                'size' => 'nullable|string|max:50',
                'color' => 'nullable|string|max:50',
                'stock' => 'nullable|integer|min:0',
                'status' => 'nullable|string|max:50',
                'images' => 'sometimes|nullable|array|min:1|max:4',
                'images.*' => 'image|mimes:jpg,jpeg,png|max:5120',
            ]);
            // Capture state before update for logging
            $oldValues = $variant->load('images')->toArray();
            $variant->update(array_filter([
                'product_id' => $validated['product_id'] ?? null,
                'sku' => $validated['sku'] ?? null,
                'size' => $validated['size'] ?? null,
                'color' => $validated['color'] ?? null,
                'stock' => $validated['stock'] ?? null,
                'status' => $validated['status'] ?? null,
            ]));
            //imageKit 
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

                // upload new images (1-4)
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
            // Capture new state and log
            $updatedVariant = $variant->fresh('images');
            $this->systemLogService->log(
                action: 'update',
                module: 'ProductVariant',
                description: "Updated product variant: {$updatedVariant->sku}",
                entity: $updatedVariant,
                oldValues: $oldValues,
                newValues: $updatedVariant->toArray()
            );
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
    public function destroy($id)
    {
        if (auth()->user()->role !== 'super-admin' && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Only super-admin and admin can delete product variant'
            ], 403);
        }
        $variant = ProductVariant::with('images')->find($id);

        if (!$variant) {
            return response()->json([
                'message' => 'Product Variant not found'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $oldValues = $variant->toArray();
            // ✅ Delete all images (ImageKit + DB)
            foreach ($variant->images as $image) {

                if ($image->image_file_id) {
                    $this->imageKit->deleteFile($image->image_file_id);
                }

                $image->delete();
            }
            // Record log prior to deleting primary record
            $this->systemLogService->log(
                action: 'delete',
                module: 'ProductVariant',
                description: "Deleted product variant: {$variant->sku}",
                entity: $variant,
                oldValues: $oldValues,
                newValues: null
            );
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