<?php

namespace App\Http\Controllers\api\v1\Marketing;

use App\Events\DiscountCreated;
use App\Events\DiscountDeleted;
use App\Events\DiscountUpdated;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Discount;
use App\Models\DiscountItem;
use App\Models\Notification;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ImageKit\ImageKit;
use App\Traits\NotifiesUsers;

class DiscountController extends Controller
{
    use NotifiesUsers;

    private function authorizeAdminOrStaff(): void
    {
        if (!in_array(auth()->user()->role, ['super-admin', 'admin'])) {
            abort(403, 'Only super-admin and admin can access this.');
        }
    }

    private function calculateDiscountPrice($price, Discount $discount): float
    {
        $price = (float) $price;
        $amount = (float) $discount->amount;

        $discountPrice = $discount->type === 'percent'
            ? $price - ($price * $amount / 100)
            : $price - $amount;

        return max(0, round($discountPrice, 2));
    }


    private function syncProductPrices(Discount $discount): void
    {
        $isCurrentlyActive = $discount->is_active
            && (!$discount->start_date || $discount->start_date->isPast())
            && (!$discount->end_date || $discount->end_date->isFuture());

        foreach ($discount->products as $product) {
            $product->update([
                'discount_price' => $isCurrentlyActive
                    ? $this->calculateDiscountPrice($product->price, $discount)
                    : 0,
            ]);
        }
    }

    public function index(Request $request)
    {
        $now = now();

        $discounts = Discount::with(['products', 'coupon'])
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            })
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'message' => $discounts->isEmpty()
                ? 'No active discounts found'
                : 'Active discounts retrieved successfully',
            'discount' => $discounts,
        ]);
    }

    // Get all discounts regardless of active status
    public function getAll(Request $request)
    {
        $discounts = Discount::with(['products', 'coupon'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'message' => $discounts->isEmpty()
                ? 'No discounts found'
                : 'All discounts retrieved successfully',
            'discount' => $discounts,
        ]);
    }

    public function show($id)
    {
        $discount = Discount::with('products')->find($id);

        if (!$discount) {
            return response()->json(['message' => 'Discount not found'], 404);
        }

        $discount->products->transform(function ($product) use ($discount) {
            // Only apply discount price if the discount is active
            $product->discount_price = $discount->is_active
                ? $this->calculateDiscountPrice($product->price, $discount)
                : 0;
            return $product;
        });

        return response()->json([
            'message' => 'Discount retrieved successfully',
            'data' => $discount
        ]);
    }

    // Create a new discount
    public function store(Request $request)
    {
        $this->authorizeAdminOrStaff();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:percent,fixed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'banner_image' => 'nullable|image|max:5120',
            'is_active' => 'nullable|boolean'
        ]);

        $slug = Str::slug($validated['name']);
        $count = Discount::where('slug', 'like', "{$slug}%")->count();
        $slug = $count ? "{$slug}-{$count}" : $slug;

        $imageUrl = null;
        $imageFileId = null;

        if ($request->hasFile('banner_image')) {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('banner_image')->getRealPath(), "r"),
                'fileName' => $request->file('banner_image')->getClientOriginalName(),
                'folder' => '/banner_images',
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

        $discount = Discount::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'amount' => $validated['amount'],
            'type' => $validated['type'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'banner_image' => $imageUrl,
            'image_file_id' => $imageFileId,
            'is_active' => $validated['is_active'] ?? false,
        ]);

        $discount->load(['products', 'coupon']);


        event(new DiscountCreated($discount));


        if ($discount->is_active) {
            $amountStr = $discount->type === 'percent'
                ? $discount->amount . '% off'
                : '$' . number_format($discount->amount, 2) . ' off';

            $message = 'Get ' . $amountStr . ' on selected products';

            if ($discount->end_date) {
                $message .= '. Expires ' . $discount->end_date->format('M d, Y');
            }

            $message .= '.';
            $this->notifyAllUsers('New Promotion!', $message, 'promotion', 'discount', '/promotions');
        }

        return response()->json([
            'message' => 'Discount created successfully',
            'data' => $discount
        ], 201);
    }

    // Update an existing discount
    public function update(Request $request, $id)
    {
        $this->authorizeAdminOrStaff();

        $discount = Discount::with('products')->find($id);

        if (!$discount) {
            return response()->json([
                'message' => 'Discount not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'sometimes|numeric|min:0',
            'type' => 'sometimes|in:percent,fixed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'banner_image' => 'nullable|image|max:5120',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->filled('name')) {
            $slug = Str::slug($request->name);

            $count = Discount::where('slug', 'like', "{$slug}%")
                ->where('id', '!=', $discount->id)
                ->count();

            $validated['slug'] = $count ? "{$slug}-{$count}" : $slug;
        }

        // Upload image to ImageKit if provided
        if ($request->hasFile('banner_image')) {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );

            $upload = $imageKit->upload([
                'file' => fopen($request->file('banner_image')->getRealPath(), "r"),
                'fileName' => $request->file('banner_image')->getClientOriginalName(),
                'folder' => '/banner_images',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            if ($discount->image_file_id) {
                try {
                    $imageKit->deleteFile($discount->image_file_id);
                } catch (\Exception $e) {

                }
            }

            $validated['banner_image'] = $upload->result->url;
            $validated['image_file_id'] = $upload->result->fileId;
        }

        return DB::transaction(function () use ($discount, $validated) {
            $discount->update($validated);
            $discount->refresh()->load('products');

            $this->syncProductPrices($discount);

            $discount->load(['products', 'coupon']);

            event(new DiscountUpdated('updated', $discount));

            return response()->json([
                'message' => 'Discount and products updated successfully',
                'data' => $discount
            ]);
        });
    }

    // Delete a discount
    public function destroy($discountId)
    {
        $this->authorizeAdminOrStaff();

        $discount = Discount::with('products')->find($discountId);

        if (!$discount) {
            return response()->json(['message' => 'Not found'], 404);
        }

        DB::transaction(function () use ($discount) {
            foreach ($discount->products as $product) {
                $product->update(['discount_price' => 0]);
            }


            $discount->products()->detach();

            $discount->delete();
        });

        event(new DiscountDeleted((int) $discountId));

        return response()->json([
            'message' => 'Discount deleted successfully'
        ]);
    }

    // Add products to a discount
    public function addProducts(Request $request)
    {
        $this->authorizeAdminOrStaff();

        $validated = $request->validate([
            'discount_id' => 'required|exists:discounts,id',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $discount = Discount::with('products')->findOrFail($validated['discount_id']);

        $discount->products()->syncWithoutDetaching($validated['product_ids']);
        $discount->refresh()->load('products');

        $this->syncProductPrices($discount);

        return response()->json([
            'message' => 'Products added to discount successfully',
            'discount' => $discount->load('products')
        ]);
    }

    // Remove a product from a discount
    public function removeProduct(Request $request, $discountId)
    {
        $this->authorizeAdminOrStaff();

        $discount = Discount::find($discountId);

        if (!$discount) {
            return response()->json(['message' => 'Discount not found'], 404);
        }

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        if (!$discount->products()->where('products.id', $validated['product_id'])->exists()) {
            return response()->json([
                'message' => 'Product not found or not associated with this discount.'
            ], 404);
        }

        $discount->products()->detach($validated['product_id']);

        Product::where('id', $validated['product_id'])->update(['discount_price' => 0]);

        return response()->json([
            'message' => "Product {$validated['product_id']} removed from discount successfully."
        ]);
    }


    public function removeProductId($productId)
    {
        $this->authorizeAdminOrStaff();

        $discountItem = DiscountItem::where('product_id', $productId)->first();

        if (!$discountItem) {
            return response()->json([
                'message' => 'Product not found in any discount'
            ], 404);
        }

        DB::transaction(function () use ($discountItem, $productId) {
            $discountItem->delete();
            Product::where('id', $productId)->update(['discount_price' => 0]);
        });

        return response()->json([
            'message' => 'Product removed from discount successfully'
        ]);
    }
}