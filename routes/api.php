<?php

use App\Http\Controllers\api\v1\CarouselController;
use App\Http\Controllers\api\v1\GoogleAuthController;
use App\Http\Controllers\api\v1\ImageController;
use App\Http\Controllers\api\v1\ParentCategoryController;
use App\Http\Controllers\api\v1\AuthController;
use App\Http\Controllers\api\v1\BrandController;
use App\Http\Controllers\api\v1\CartController;
use App\Http\Controllers\api\v1\CategoryController;
use App\Http\Controllers\api\v1\OrderController;
use App\Http\Controllers\api\v1\ProductController;
use App\Http\Controllers\api\v1\ProfileController;
use App\Http\Controllers\api\v1\ReviewController;
use App\Http\Controllers\api\v1\UserController;
use App\Http\Controllers\api\v1\WishlistController;
use App\Models\Carousel;
use Illuminate\Support\Facades\Route;
Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    // Email Verification
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendCode']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Google OAuth 
    Route::get('/auth/google', [GoogleAuthController::class, 'redirectGoogle']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
    // Public routes
    Route::prefix('public')->group(function () {
        // Carousel
        Route::get('/carousels', [CarouselController::class, 'index']);
        //  Products
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'searchById']);
        Route::get('/public/products/slug/{slug}', [ProductController::class, 'searchBySlug']);

        // Categories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{id}', [CategoryController::class, 'showById']);
        Route::get('/categories/slug/{slug}', [CategoryController::class, 'showBySlug']);

        Route::get('/parent-categories', [ParentCategoryController::class, 'index']);
        Route::get('/parent-categories/search', [ParentCategoryController::class, 'searchName']);
        Route::get('/parent-categories/{id}', [ParentCategoryController::class, 'search']);

        //  Brands
        Route::get('/brands', [BrandController::class, 'index']);
        Route::get('/brands/{id}', [BrandController::class, 'showById']);
        Route::get('/brands/slug/{slug}', [BrandController::class, 'showBySlug']);
    });


    //  protected routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'me']);
        Route::put('/profile/update-name', [ProfileController::class, 'updateName']);
        Route::post('/profile/upload-image', [ImageController::class, 'uploadProfileImage']);
        Route::get('/profile/images', [ProfileController::class, 'getProfileImage']);
        Route::put('/profile/change-password', [AuthController::class, 'changePassword']);
        // Carts 
        Route::get('/cart', [CartController::class, 'index']);
        Route::post('/cart/add', [CartController::class, 'store']);
        // Update Cart Item
        Route::put('/cart/item/{id}', [CartController::class, 'updateItem']);
        Route::delete('/cart/item/{id}', [CartController::class, 'destroy']);
        Route::delete('/cart', [CartController::class, 'clearCart']);

        // Checkout
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::get('/my-order', [OrderController::class, 'myOrders']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        // Wishlist
        Route::prefix('wishlists')->group(function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::post('/', [WishlistController::class, 'store']);
            Route::delete('/{id}', [WishlistController::class, 'destroy']);
        });
        // Review
        Route::prefix('reviews')->group(function () {
            Route::get('/', [ReviewController::class, 'index']);
            Route::post('/', [ReviewController::class, 'store']);
            Route::put('/{id}', [ReviewController::class, 'update']);
            Route::delete('/{id}', [ReviewController::class, 'destroy']);
        });

        // Admin route
        Route::middleware(['role:admin'])->prefix('admin')->group(function () {
            //Carousel 
            Route::post('/carousels', [CarouselController::class, 'store']);
            Route::post('/carousels/{id}/image', [CarouselController::class, 'updateImage']);
            Route::delete('/carousels/{id}', [CarouselController::class, 'destroy']);
            // Products
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);

            // Categories
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

            // Brands
            Route::post('/brands', [BrandController::class, 'store']);
            Route::put('/brands/{id}', [BrandController::class, 'update']);
            Route::delete('/brands/{id}', [BrandController::class, 'destroy']);
            // Parent Category
            Route::post('/parent-categories', [ParentCategoryController::class, 'store']);
            Route::put('/parent-categories/{id}', [ParentCategoryController::class, 'update']);
            Route::delete('/parent-categories/{id}', [ParentCategoryController::class, 'destroy']);


            // Admin route to see orders of a specific user
            Route::get('/users', [UserController::class, 'index']);
            Route::get('/user/{user}/orders', [OrderController::class, 'userOrderHistory']);

            Route::post('/categories/upload-image/{id}', [CategoryController::class, 'uploadImageToCategory']);
            Route::post('/products/upload-image/{id}', [ProductController::class, 'uploadImageToProduct']);
        });

    });
});

