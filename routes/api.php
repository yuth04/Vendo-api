<?php



use App\Http\Controllers\api\v1\Customer\AddressController;
use App\Http\Controllers\api\v1\Admin\DashboardController;
use App\Http\Controllers\api\v1\Admin\PosController;
use App\Http\Controllers\api\v1\Admin\SettingController;
use App\Http\Controllers\api\v1\Auth\AuthController;
use App\Http\Controllers\api\v1\BrandController;
use App\Http\Controllers\api\v1\Marketing\DiscountController;
use App\Http\Controllers\api\v1\Marketing\CarouselController;
use App\Http\Controllers\api\v1\Customer\CartController;
use App\Http\Controllers\api\v1\CategoryController;
use App\Http\Controllers\api\v1\Auth\GoogleAuthController;
use App\Http\Controllers\api\v1\Marketing\DiscountCategoryController;
use App\Http\Controllers\api\v1\Orders\OrderController;
use App\Http\Controllers\api\v1\ParentCategoryController;
use App\Http\Controllers\api\v1\Orders\PaymentController;
use App\Http\Controllers\api\v1\PermissionController;
use App\Http\Controllers\api\v1\ProductController;
use App\Http\Controllers\api\v1\ProductVariantController;
use App\Http\Controllers\api\v1\Customer\ProfileController;
use App\Http\Controllers\api\v1\Orders\ReturnOrderController;
use App\Http\Controllers\api\v1\ReviewController;
use App\Http\Controllers\api\v1\Admin\UpdateStatusController;
use App\Http\Controllers\api\v1\Customer\UserController;
use App\Http\Controllers\api\v1\Customer\WishlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('api/health', function () {
        return response()->json(['status' => 'ok']);
    });
    // Google OAuth
    Route::get('/auth/google', [GoogleAuthController::class, 'redirectGoogle']);
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
    Route::get('/orders/{order}/receipt/download', [OrderController::class, 'downloadReceipt'])
        ->name('order.receipt.download')
        ->middleware('signed');
    // Bakong Payment
    Route::get('/payment/qr/{orderId}', [PaymentController::class, 'generateBakongQr']);
    Route::post('/payment/verify', [PaymentController::class, 'verifyPayment']);
    // ------------------- AUTH -------------------
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    // Email Verifications
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-code', [AuthController::class, 'verifyCode']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    // Reviews
    Route::get('/reviews', [ReviewController::class, 'index']);
    Route::get('/', [CartController::class, 'index']);
    // ------------------- PUBLIC -------------------
    Route::prefix('public')->group(function () {

        Route::get('/products/{id}/reviews-summary', [ReviewController::class, 'productSummary']);
        // Carousels
        Route::get('/carousels', [CarouselController::class, 'index']);

        // Products
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'searchById']);
        Route::get('/products/slug/{slug}', [ProductController::class, 'searchBySlug']);

        // Categories
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/categories/{id}', [CategoryController::class, 'showById']);
        Route::get('/categories/slug/{slug}', [CategoryController::class, 'showBySlug']);
        Route::get('/parent-categories', [ParentCategoryController::class, 'index']);
        Route::get('/parent-categories/search', [ParentCategoryController::class, 'searchName']);
        Route::get('/parent-categories/{id}', [ParentCategoryController::class, 'search']);

        // Brands
        Route::get('/brands', [BrandController::class, 'index']);
        Route::get('/brands/{id}', [BrandController::class, 'showById']);
        Route::get('/brands/slug/{slug}', [BrandController::class, 'showBySlug']);

        // Product Variants
        Route::get('/product-variants', [ProductVariantController::class, 'index']);
        Route::get('/product-variants/{id}', [ProductVariantController::class, 'show']);

        // Discounts
        Route::prefix('discounts')->group(function () {
            Route::get('/', [DiscountController::class, 'index']);
        });
        Route::get('/discount-categories', [DiscountCategoryController::class, 'index']);
        Route::get('/discount-categories/{id}', [DiscountCategoryController::class, 'show']);

        // Provinces
        Route::get('/provinces', [AddressController::class, 'provinces']);


    });

    // ------------------- PROTECTED -------------------
    Route::middleware(['auth:sanctum'])->group(function () {
        // Return Order
        Route::post('/return-orders', [ReturnOrderController::class, 'store']);
        Route::get('/return-orders', [ReturnOrderController::class, 'index']);
        Route::get('/return-orders/{id}', [ReturnOrderController::class, 'show']);
        // Auth
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'me']);
        Route::put('/profile/update-name', [ProfileController::class, 'updateName']);
        Route::post('/profile/upload-image', [ProfileController::class, 'uploadProfileImage']);
        Route::get('/profile/images', [ProfileController::class, 'getProfileImage']);
        Route::put('/profile/change-password', [AuthController::class, 'changePassword']);

        Route::prefix('cart')->group(function () {

            Route::post('/', [CartController::class, 'store']);
            Route::put('/item/{id}', [CartController::class, 'updateItem']);
            Route::delete('/item/{id}', [CartController::class, 'destroy']);
            Route::delete('/clear', [CartController::class, 'clearCart']);
        });
        // Checkout & Orders
        Route::post('/checkout', [OrderController::class, 'checkout']);
        Route::get('/orders', [OrderController::class, 'myOrders']);
        Route::delete('/orders/cancel/{order}', [OrderController::class, 'cancel']);
        Route::delete('/orders/{orderId}', [OrderController::class, 'destroy']);

        // Wishlists
        Route::prefix('wishlists')->group(function () {
            Route::get('/', [WishlistController::class, 'index']);
            Route::post('/', [WishlistController::class, 'store']);
            Route::delete('/{id}', [WishlistController::class, 'destroy']);

        });

        // Reviews
        Route::prefix('reviews')->group(function () {
            Route::post('/', [ReviewController::class, 'store']);
            Route::post('/{id}', [ReviewController::class, 'update']);
            Route::delete('/{id}', [ReviewController::class, 'destroy']);
        });

        // Addresses
        Route::prefix('addresses')->group(function () {
            Route::get('/', [AddressController::class, 'index']);
            Route::post('/', [AddressController::class, 'store']);
            Route::put('/{id}', [AddressController::class, 'update']);
            Route::delete('/{id}', [AddressController::class, 'destroy']);
        });

        // ------------------- ADMIN ROUTES -------------------
        Route::middleware(['role:admin'])->prefix('admin')->group(function () {
            //  Updater Status
            Route::patch('/orders/confirm/{id}', [UpdateStatusController::class, 'markAsConfirmed']);
            Route::patch('/orders/ship/{id}', [UpdateStatusController::class, 'markAsShipping']);
            Route::patch('/orders/deliver/{id}', [UpdateStatusController::class, 'markAsDelivered']);
            Route::put('/orders/payment/{id}', [UpdateStatusController::class, 'updatePaymentStatus']);
            // Returns
            Route::get('/returns', [ReturnOrderController::class, 'getAll']);
            Route::patch('/returns/approve/{id}', [ReturnOrderController::class, 'approve']);
            Route::patch('/returns/reject/{id}', [ReturnOrderController::class, 'reject']);
            Route::patch('/returns/complete/{id}', [ReturnOrderController::class, 'complete']);

            // Filter orders by status
            Route::get('/orders/{status}', [OrderController::class, 'filterByStatus']);
            // Admin
            Route::patch('/reviews/approve/{id}', [ReviewController::class, 'approve']);

            // Discounts
            Route::get('/discounts/{id}', [DiscountController::class, 'show']);
            Route::post('/discounts', [DiscountController::class, 'store']);
            Route::put('/discounts/{id}', [DiscountController::class, 'update']);
            Route::delete('/discounts/{id}', [DiscountController::class, 'destroy']);
            Route::post('/discount-categories', [DiscountCategoryController::class, 'store']);
            Route::post('/discount-categories/{id}', [DiscountCategoryController::class, 'update']);
            Route::delete('/discount-categories/{id}', [DiscountCategoryController::class, 'destroy']);
            Route::post('/discounts/add-product/{id}', [DiscountController::class, 'addProducts']);
            Route::post('/discounts/remove-product/{discountId}', [DiscountController::class, 'removeProduct']);

            // Carousels
            Route::post('/carousels', [CarouselController::class, 'store']);
            Route::post('/carousels/{id}', [CarouselController::class, 'update']);
            Route::delete('/carousels/{id}', [CarouselController::class, 'destroy']);

            // Products
            Route::post('/products', [ProductController::class, 'store']);
            Route::post('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);
            // Product Variant images
            Route::post('/product-variants', [ProductVariantController::class, 'store']);
            Route::post('/product-variants/{id}', [ProductVariantController::class, 'update']);
            Route::delete('/product-variants/{id}', [ProductVariantController::class, 'destroy']);

            // Categories
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::post('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
            Route::post('/categories/upload-image/{id}', [CategoryController::class, 'uploadImageToCategory']);

            // Brands
            Route::post('/brands', [BrandController::class, 'store']);
            Route::post('/brands/{id}', [BrandController::class, 'update']);
            Route::delete('/brands/{id}', [BrandController::class, 'destroy']);

            // Parent Categories
            Route::post('/parent-categories', [ParentCategoryController::class, 'store']);
            Route::put('/parent-categories/{id}', [ParentCategoryController::class, 'update']);
            Route::delete('/parent-categories/{id}', [ParentCategoryController::class, 'destroy']);

            // Admin view of user orders
            Route::get('/pos-orders', [PosController::class, 'orderHistory']);
            Route::get('/user/orders', [UserController::class, 'userOrder']);
            Route::get('/orders', [OrderController::class, 'filterByStatus']);
            Route::put('/users/role/{id}', [PermissionController::class, 'updateRoleUser']);
            // Admin
            Route::get('/users/admins', [UserController::class, 'getAllAdmin']);
            // User
            Route::get('/users', [UserController::class, 'getAllUser']);
            Route::get('/users/{id}', [UserController::class, 'searchUser']);
            Route::post('/users/{id}', [UserController::class, 'updateUser']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);
            // Admin Setting
            Route::put('/settings/change-name', [SettingController::class, 'changeName']);
            Route::put('/settings/change-password', [SettingController::class, 'changePassword']);
            Route::post('/settings/change-avatar', [SettingController::class, 'changeProfileImage']);

            // Dashboard Stats
            Route::prefix('/dashboard')->group(function () {
                Route::get('/', [DashboardController::class, 'index']);
                Route::get('/stats', [DashboardController::class, 'stats']);
                Route::get('/order-stats', [DashboardController::class, 'orderStat']);
                Route::get('/most-sold-products', [DashboardController::class, 'mostSoldProducts']);
                Route::get('/most-sold-brands', [DashboardController::class, 'mostSoldBrands']);
                // Stocks
                Route::get('/stocks', [DashboardController::class, 'stock']);
                Route::get('/stock-alert', [DashboardController::class, 'stockAlert']);
            });
        });

    });
});