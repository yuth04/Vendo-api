<?php

namespace App\Http\Controllers\api\v1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
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
}
