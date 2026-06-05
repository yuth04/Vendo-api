<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Carousel;
use App\Models\Category;
use App\Models\DiscountCategory;
use App\Models\ParentCategory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class ProductPermissionController extends Controller
{
    public function index(Request $request)
    {

        $products = Product::with(['category', 'brand', 'variants'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'message' => 'Retrieved products successfully',
            'product' => $products
        ]);

    }
    public function productId($id)
    {
        $product = Product::with(['category', 'brand', 'variants'])->find($id);
        if (!$product) {
            return response()->json([
                'message' => "Product not found"
            ]);
        }
        return response()->json([
            'message' => 'Retrieved products successfully',
            'product' => $product
        ]);

    }
    public function parentCategory()
    {
        $baseCategories = ParentCategory::with(['categories.products'])

            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($base) {
                return [
                    'id' => $base->id,
                    'name' => $base->name,
                    'status' => $base->status,
                    'created_at' => $base->created_at->format('d-m-Y g:i:s A'),
                    'updated_at' => $base->updated_at->format('d-m-Y g:i:s A'),
                    'categories' => $base->categories->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                            'is_active' => $category->is_active,
                            'parent_category_id' => $category->parent_category_id,
                            'created_at' => $category->created_at->format('d-m-Y g:i:s A'),
                            'updated_at' => $category->updated_at->format('d-m-Y g:i:s A'),
                            'image' => $category->image,
                            'products' => $category->products->map(function ($product) {
                                return [
                                    'id' => $product->id,
                                    'productName' => $product->productName,
                                    'slug' => $product->slug,
                                    'description' => $product->description,
                                    'price' => $product->price,
                                    'category_id' => $product->category_id,
                                    'brand_id' => $product->brand_id,
                                    'created_at' => $product->created_at->format('d-m-Y g:i:s A'),
                                    'updated_at' => $product->updated_at->format('d-m-Y g:i:s A'),
                                    'image' => $product->image,
                                ];
                            }),
                        ];
                    }),
                ];
            });

        return response()->json([
            'message' => 'Retrieved base-category successfully',
            'data' => $baseCategories
        ], 200);
    }
    public function parentCategoryId($id)
    {
        $base = ParentCategory::with(['categories.products'])
            ->findOrFail($id);
        $data = [
            'id' => $base->id,
            'name' => $base->name,
            'status' => $base->status,
            'created_at' => $base->created_at,
            'updated_at' => $base->updated_at,
            'categories' => $base->categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'is_active' => $category->is_active,
                    'parent_category_id' => $category->parent_category_id,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                    'image' => $category->image,
                    'products' => $category->products->map(function ($product) {
                        return [
                            'id' => $product->id,
                            'productName' => $product->productName,
                            'slug' => $product->slug,
                            'description' => $product->description,
                            'price' => $product->price,
                            'category_id' => $product->category_id,
                            'brand_id' => $product->brand_id,
                            'created_at' => $product->created_at,
                            'updated_at' => $product->updated_at,
                            'image' => $product->image,
                        ];
                    }),
                ];
            }),
        ];

        return response()->json([
            'message' => 'Retrieved base-category successfully',
            'data' => $data
        ], 200);
    }
    public function variant()
    {
        $productVariants = ProductVariant::with('product', 'images')->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => 'Product Variants retrieved successfully',
            'product_variant' => $productVariants
        ]);
    }
    public function variantId($id)
    {
        $productVariants = ProductVariant::with('product', 'images')->findOrFail($id);
        return response()->json([
            'message' => 'Product Variants retrieved successfully',
            'product_variant' => $productVariants
        ]);
    }
    public function category()
    {
        $categories = Category::with('products')
            ->orderBy('id', 'asc')
            ->get();
        return response()->json([
            'message' => "Retrieved categories successfully",
            'category' => $categories
        ]);
    }
    public function categoryId($id)
    {
        $categories = Category::with('products')->findOrFail($id);
        return response()->json([
            'message' => "Retrieved categories successfully",
            'category' => $categories
        ]);
    }
    public function brand()
    {
        $brand = Brand::with(['products'])->orderBy('id', 'asc')->get();
        return response()->json([
            'message' => "get brands successfully",
            'brands' => $brand
        ]);
    }
    public function brandId($id)
    {
        $brand = Brand::with(['products'])->find($id);
        return response()->json([
            'message' => "get brands successfully",
            'brands' => $brand
        ]);
    }
    public function discountCategory()
    {
        $category = DiscountCategory::with('discounts.products', 'coupons')->orderBy('id', 'asc')->get();

        if (!$category) {
            return response()->json([
                'message' => "No discount category found"
            ], 404);
        }

        return response()->json([
            'message' => 'Discount category fetched successfully',
            'data' => $category
        ]);
    }
    public function discountCategoryId($id)
    {
        $category = DiscountCategory::with('discounts.products', 'coupons')->find($id);

        if (!$category) {
            return response()->json([
                'message' => "No discount category found"
            ], 404);
        }

        return response()->json([
            'message' => 'Discount category fetched successfully',
            'data' => $category
        ]);
    }
    public function carousel()
    {
        $carousels = Carousel::orderBy('position')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'message' => 'Carousels retrieved successfully',
            'carousels' => $carousels
        ]);
    }
    public function carouselId($id)
    {
        $carousels = Carousel::orderBy('position')
            ->find($id);
        if (!$carousels) {
            return response()->json(['message' => 'Carousel not found']);
        }

        return response()->json([
            'message' => 'Carousels retrieved successfully',
            'carousels' => $carousels
        ]);
    }
}
