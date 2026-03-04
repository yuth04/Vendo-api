<?php

namespace App\Http\Controllers\api\v2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function pagination(Request $request)
    {

        $query = ProductController::with(['category', 'brand', 'variants']);

        if ($request->filled('search')) {
            $query->where('productName', 'ilike', '%' . $request->search . '%');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        $perPage = $request->get('per_page', 10);
        $products = $query->paginate($perPage);


        return response()->json([
            'message' => 'Fetched products successfully',
            'product' => $products
        ]);

    }
}
