<?php

use App\Http\Controllers\api\v1\Admin\AdminProductController;
use App\Http\Controllers\api\v1\Admin\CouponController;
use App\Http\Controllers\api\v1\Admin\NotificationController;
use App\Http\Controllers\api\v1\Admin\ProductDamageController;
use App\Http\Controllers\api\v1\Admin\ProductPermissionController;
use App\Http\Controllers\api\v1\Customer\AddressController;
use App\Http\Controllers\api\v1\Admin\DashboardController;
use App\Http\Controllers\api\v1\Admin\PosController;
use App\Http\Controllers\api\v1\Admin\SettingController;
use App\Http\Controllers\api\v1\Auth\AuthController;
use App\Http\Controllers\api\v1\BrandController;
use App\Http\Controllers\api\v1\Export\ExportController;
use App\Http\Controllers\api\v1\Marketing\DiscountController;
use App\Http\Controllers\api\v1\Marketing\CarouselController;
use App\Http\Controllers\api\v1\Customer\CartController;
use App\Http\Controllers\api\v1\CategoryController;
use App\Http\Controllers\api\v1\Auth\GoogleAuthController;
use App\Http\Controllers\api\v1\Marketing\DiscountCategoryController;
use App\Http\Controllers\api\v1\Orders\OrderController;
use App\Http\Controllers\api\v1\ParentCategoryController;
use App\Http\Controllers\api\v1\Orders\PaymentController;
use App\Http\Controllers\api\v1\Admin\PermissionController;
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

    // =====================================================
    // HEALTH CHECK
    // =====================================================
    Route::get('/health', fn() => response()->json(['status' => 'ok']));

    // =====================================================
    // EXPORTS (No auth — signed/token handled downstream)
    // =====================================================
    Route::prefix('export')->group(function () {
        Route::get('/orders/excel',           [ExportController::class, 'exportOrderHistory']);
        Route::get('/orders/pdf',             [ExportController::class, 'orderHistoryPdf']);
        Route::get('/products/excel',         [ExportController::class, 'exportProducts']);
        Route::get('/products/pdf',           [ExportController::class, 'productHistoryPdf']);
        Route::get('/products-report/excel',  [ExportController::class, 'exportProductsReport']);
        Route::get('/products-report/pdf',    [ExportController::class, 'exportProductsPdf']);
        Route::get('/sales-report/excel',     [ExportController::class, 'exportSalesReport']);
        Route::get('/sales-report/pdf',       [ExportController::class, 'exportSalesReportPdf']);
        Route::get('/returns/excel',          [ExportController::class, 'exportReturnHistoryExcel']);
        Route::get('/returns/pdf',            [ExportController::class, 'exportReturnHistoryPdf']);
        Route::get('/customers/excel',        [ExportController::class, 'exportUsersExcel']);
        Route::get('/customers/pdf',          [ExportController::class, 'exportUsersPDF']);
        Route::get('/admins/excel',           [ExportController::class, 'exportAdminsExcel']);
        Route::get('/admins/pdf',             [ExportController::class, 'exportAdminsPDF']);
        Route::get('/stocks/excel',           [ExportController::class, 'exportStock']);
        Route::get('/stocks/pdf',             [ExportController::class, 'exportStockPDF']);
        Route::get('/variants/excel',         [ExportController::class, 'exportVariantsExcel']);
        Route::get('/variants/pdf',           [ExportController::class, 'exportVariantPDF']);
        Route::get('/product-damages/excel',  [ExportController::class, 'exportProductDamageExcel']);
        Route::get('/product-damages/pdf',    [ExportController::class, 'exportProductDamagePdf']);
    });

    // =====================================================
    // GOOGLE OAUTH
    // =====================================================
    Route::prefix('auth/google')->group(function () {
        Route::get('/',          [GoogleAuthController::class, 'redirectGoogle']);
        Route::get('/callback',  [GoogleAuthController::class, 'handleGoogleCallback']);
    });

    // =====================================================
    // PAYMENT (Public — webhook & QR generation)
    // =====================================================
    Route::prefix('payment')->group(function () {
        Route::get('/qr/{orderId}',  [PaymentController::class, 'generateBakongQr']);
        Route::post('/verify',       [PaymentController::class, 'verifyPayment']);
    });

    // =====================================================
    // AUTH (Public)
    // =====================================================
    Route::prefix('auth')->group(function () {
        Route::post('/register',         [AuthController::class, 'register']);
        Route::post('/login',            [AuthController::class, 'login'])->name('login');
        Route::post('/verify-email',     [AuthController::class, 'verifyEmail']);
        Route::post('/forgot-password',  [AuthController::class, 'forgotPassword']);
        Route::post('/verify-code',      [AuthController::class, 'verifyCode']);
        Route::post('/reset-password',   [AuthController::class, 'resetPassword']);
    });

    // =====================================================
    // PUBLIC (No auth required)
    // =====================================================
    Route::prefix('public')->group(function () {

        // Products
        Route::prefix('products')->group(function () {
            Route::get('/',                      [ProductController::class, 'index']);
            Route::get('/most-sold',             [ProductController::class, 'mostSoldProducts']);
            Route::get('/{id}',                  [ProductController::class, 'searchById']);
            Route::get('/slug/{slug}',           [ProductController::class, 'searchBySlug']);
            Route::get('/{id}/reviews-summary',  [ReviewController::class, 'productSummary']);
        });

        // Categories
        Route::prefix('categories')->group(function () {
            Route::get('/',             [CategoryController::class, 'index']);
            Route::get('/{id}',         [CategoryController::class, 'showById']);
            Route::get('/slug/{slug}',  [CategoryController::class, 'showBySlug']);
        });

        // Parent Categories
        Route::prefix('parent-categories')->group(function () {
            Route::get('/',          [ParentCategoryController::class, 'index']);
            Route::get('/search',    [ParentCategoryController::class, 'searchName']);
            Route::get('/{id}',      [ParentCategoryController::class, 'search']);
        });

        // Brands
        Route::prefix('brands')->group(function () {
            Route::get('/',             [BrandController::class, 'index']);
            Route::get('/{id}',         [BrandController::class, 'showById']);
            Route::get('/slug/{slug}',  [BrandController::class, 'showBySlug']);
        });

        // Product Variants
        Route::prefix('product-variants')->group(function () {
            Route::get('/',      [ProductVariantController::class, 'index']);
            Route::get('/{id}',  [ProductVariantController::class, 'show']);
        });

        // Discounts
        Route::prefix('discounts')->group(function () {
            Route::get('/',      [DiscountController::class, 'index']);
            Route::get('/{id}',  [DiscountCategoryController::class, 'show']);
        });

        Route::get('/discount-categories',      [DiscountCategoryController::class, 'index']);
        Route::get('/discount-categories/{id}', [DiscountCategoryController::class, 'show']);

        // Carousels
        Route::get('/carousels',  [CarouselController::class, 'index']);

        // Reviews
        Route::get('/reviews',  [ReviewController::class, 'index']);

        // Address / Provinces
        Route::get('/provinces',  [AddressController::class, 'provinces']);
    });

    // =====================================================
    // PROTECTED (Auth required for all below)
    // =====================================================
    Route::middleware(['auth:sanctum', 'check.status'])->group(function () {

        // Order Receipt (Signed URL)
        Route::get('/orders/{order}/receipt/download', [OrderController::class, 'downloadReceipt'])
            ->name('order.receipt.download')
            ->middleware('signed');

        // -------------------------------------------------
        // CUSTOMER ROUTES (Any authenticated user)
        // -------------------------------------------------

        // Auth / Profile
        Route::post('/logout',                    [AuthController::class, 'logout']);
        Route::get('/profile',                    [AuthController::class, 'me']);
        Route::put('/profile/change-password',    [AuthController::class, 'changePassword']);
        Route::put('/profile/update-name',        [ProfileController::class, 'updateName']);
        Route::post('/profile/upload-image',      [ProfileController::class, 'uploadProfileImage']);
        Route::get('/profile/images',             [ProfileController::class, 'getProfileImage']);

        // Cart
        Route::prefix('cart')->group(function () {
            Route::get('/',             [CartController::class, 'index']);
            Route::post('/',            [CartController::class, 'store']);
            Route::put('/item/{id}',    [CartController::class, 'updateItem']);
            Route::delete('/item/{id}', [CartController::class, 'destroy']);
            Route::delete('/clear',     [CartController::class, 'clearCart']);
        });

        // Checkout & Orders
        Route::post('/checkout',                  [OrderController::class, 'checkout']);
        Route::get('/orders',                     [OrderController::class, 'myOrders']);
        Route::delete('/orders/cancel/{order}',   [OrderController::class, 'cancel']);
        Route::delete('/orders/{orderId}',        [OrderController::class, 'destroy']);

        // Coupon
        Route::post('/apply-coupon',   [OrderController::class, 'apply']);
        Route::post('/clear-coupon',   [OrderController::class, 'clear']);

        // Return Orders
        Route::prefix('return-orders')->group(function () {
            Route::get('/',      [ReturnOrderController::class, 'index']);
            Route::get('/{id}',  [ReturnOrderController::class, 'show']);
            Route::post('/',     [ReturnOrderController::class, 'store']);
        });

        // Wishlists
        Route::prefix('wishlists')->group(function () {
            Route::get('/',      [WishlistController::class, 'index']);
            Route::post('/',     [WishlistController::class, 'store']);
            Route::delete('/{id}', [WishlistController::class, 'destroy']);
        });

        // Reviews
        Route::prefix('reviews')->group(function () {
            Route::post('/',      [ReviewController::class, 'store']);
            Route::post('/{id}',  [ReviewController::class, 'update']);
            Route::delete('/{id}', [ReviewController::class, 'destroy']);
        });

        // Addresses
        Route::prefix('addresses')->group(function () {
            Route::get('/',        [AddressController::class, 'index']);
            Route::post('/',       [AddressController::class, 'store']);
            Route::put('/{id}',    [AddressController::class, 'update']);
            Route::delete('/{id}', [AddressController::class, 'destroy']);
        });

        // -------------------------------------------------
        // 👑 SUPER ADMIN ROUTES
        // -------------------------------------------------
        Route::middleware(['role:admin'])->prefix('admin')->group(function () {
            // Dashboard
            Route::prefix('dashboard')->group(function () {
                Route::get('/',                  [DashboardController::class, 'dashboard']);
                Route::get('/stats',             [DashboardController::class, 'stats']);
                Route::get('/order-stats',       [DashboardController::class, 'orderStat']);
                Route::get('/most-sold-products',[DashboardController::class, 'mostSoldProducts']);
                Route::get('/most-sold-brands',  [DashboardController::class, 'mostSoldBrands']);
                Route::get('/stocks',            [DashboardController::class, 'stock']);
                Route::get('/stock-alert',       [DashboardController::class, 'stockAlert']);
            });

            // Orders
            Route::prefix('orders')->group(function () {
                Route::get('/{status}',          [OrderController::class, 'filterByStatus']);
                Route::get('/histories',         [PosController::class, 'orderHistory']);
                Route::get('/detail/{id}',       [PosController::class, 'orderDetails']);
                Route::patch('/confirm/{id}',    [UpdateStatusController::class, 'markAsConfirmed']);
                Route::patch('/ship/{id}',       [UpdateStatusController::class, 'markAsShipping']);
                Route::patch('/deliver/{id}',    [UpdateStatusController::class, 'markAsDelivered']);
                Route::patch('/complete/{id}',   [UpdateStatusController::class, 'markAsCompleted']);
                Route::put('/payment/{id}',      [UpdateStatusController::class, 'updatePaymentStatus']);
            });

            // Returns
            Route::prefix('returns')->group(function () {
                Route::get('/',              [ReturnOrderController::class, 'getAll']);
                Route::get('/detail/{id}',   [PosController::class, 'returnDetails']);
                Route::patch('/approve/{id}',[ReturnOrderController::class, 'approve']);
                Route::patch('/reject/{id}', [ReturnOrderController::class, 'reject']);
                Route::patch('/complete/{id}',[ReturnOrderController::class, 'complete']);
            });

            // Products
            Route::prefix('products')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'index']);
                Route::get('/{id}',    [ProductPermissionController::class, 'productId']);
                Route::post('/',       [ProductController::class, 'store']);
                Route::post('/{id}',   [ProductController::class, 'update']);
                Route::delete('/{id}', [ProductController::class, 'destroy']);
            });

            // Product Variants
            Route::prefix('product-variants')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'variant']);
                Route::get('/{id}',    [ProductPermissionController::class, 'variantId']);
                Route::post('/',       [ProductVariantController::class, 'store']);
                Route::post('/{id}',   [ProductVariantController::class, 'update']);
                Route::delete('/{id}', [ProductVariantController::class, 'destroy']);
            });

            // Categories
            Route::prefix('categories')->group(function () {
                Route::get('/',                    [ProductPermissionController::class, 'category']);
                Route::get('/{id}',                [ProductPermissionController::class, 'categoryId']);
                Route::post('/',                   [CategoryController::class, 'store']);
                Route::post('/{id}',               [CategoryController::class, 'update']);
                Route::post('/upload-image/{id}',  [CategoryController::class, 'uploadImageToCategory']);
                Route::delete('/{id}',             [CategoryController::class, 'destroy']);
            });

            // Brands
            Route::prefix('brands')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'brand']);
                Route::get('/{id}',    [ProductPermissionController::class, 'brandId']);
                Route::post('/',       [BrandController::class, 'store']);
                Route::post('/{id}',   [BrandController::class, 'update']);
                Route::delete('/{id}', [BrandController::class, 'destroy']);
            });

            // Parent Categories
            Route::prefix('parent-categories')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'parentCategory']);
                Route::get('/{id}',    [ProductPermissionController::class, 'parentCategoryId']);
                Route::post('/',       [ParentCategoryController::class, 'store']);
                Route::put('/{id}',    [ParentCategoryController::class, 'update']);
                Route::delete('/{id}', [ParentCategoryController::class, 'destroy']);
            });

            // Discounts
            Route::prefix('discounts')->group(function () {
                Route::get('/',                         [DiscountController::class, 'getAll']);
                Route::get('/{id}',                     [DiscountController::class, 'show']);
                Route::post('/',                        [DiscountController::class, 'store']);
                Route::put('/{id}',                     [DiscountController::class, 'update']);
                Route::delete('/{id}',                  [DiscountController::class, 'destroy']);
                Route::post('/add-products',            [DiscountController::class, 'addProducts']);
                Route::post('/remove-product/{discountId}', [DiscountController::class, 'removeProduct']);
                Route::delete('/remove-product/{productId}',[DiscountController::class, 'removeProductId']);
            });

            // Discount Categories
            Route::prefix('discount-categories')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'discountCategory']);
                Route::get('/{id}',    [ProductPermissionController::class, 'discountCategoryId']);
                Route::post('/',       [DiscountCategoryController::class, 'store']);
                Route::post('/{id}',   [DiscountCategoryController::class, 'update']);
                Route::delete('/{id}', [DiscountCategoryController::class, 'destroy']);
            });

            // Carousels
            Route::prefix('carousels')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'carousel']);
                Route::get('/{id}',    [ProductPermissionController::class, 'carouselId']);
                Route::post('/',       [CarouselController::class, 'store']);
                Route::post('/{id}',   [CarouselController::class, 'update']);
                Route::delete('/{id}', [CarouselController::class, 'destroy']);
            });

            // Coupons
            Route::prefix('coupons')->group(function () {
                Route::get('/',        [CouponController::class, 'index']);
                Route::get('/{id}',    [CouponController::class, 'getById']);
                Route::post('/',       [CouponController::class, 'store']);
                Route::post('/{id}',   [CouponController::class, 'update']);
                Route::delete('/{id}', [CouponController::class, 'destroy']);
            });

            // Reviews
            Route::prefix('reviews')->group(function () {
                Route::get('/',              [ReviewController::class, 'getAll']);
                Route::get('/{id}',          [ReviewController::class, 'getById']);
                Route::post('/reply/{id}',   [ReviewController::class, 'reply']);
                Route::put('/reply/{id}',    [ReviewController::class, 'updateReply']);
                Route::patch('/approve/{id}',[ReviewController::class, 'approved']);
                Route::delete('/{id}',       [ReviewController::class, 'delete']);
            });

            // Notifications
            Route::prefix('notifications')->group(function () {
                Route::get('/',           [NotificationController::class, 'notifications']);
                Route::get('/{id}',       [NotificationController::class, 'getById']);
                Route::post('/{id}', [NotificationController::class, 'markAsSeen']);
            });

            // Product Damages
            Route::prefix('product-damages')->group(function () {
                Route::get('/',        [ProductDamageController::class, 'index']);
                Route::post('/',       [ProductDamageController::class, 'store']);
                Route::post('/{id}',   [ProductDamageController::class, 'update']);
                Route::delete('/{id}', [ProductDamageController::class, 'destroy']);
            });

            // Reports
            Route::get('/sale-reports',     [DashboardController::class, 'salesReport']);
            Route::get('/product-reports',  [DashboardController::class, 'productsReport']);

            // Users & Roles
            Route::prefix('users')->group(function () {
                Route::get('/',         [UserController::class, 'getAllUser']);
                Route::get('/admins',   [UserController::class, 'getAllAdmin']);
                Route::get('/orders',   [UserController::class, 'userOrder']);
                Route::get('/{id}',     [UserController::class, 'searchUser']);
                Route::post('/{id}',    [UserController::class, 'updateUser']);
                Route::delete('/{id}',  [UserController::class, 'destroy']);
                Route::put('/role/{id}',[PermissionController::class, 'updateRoleUser']);
            });

            // Settings
            Route::prefix('settings')->group(function () {
                Route::put('/change-name',       [SettingController::class, 'changeName']);
                Route::put('/change-password',   [SettingController::class, 'changePassword']);
                Route::post('/change-avatar',    [SettingController::class, 'changeProfileImage']);
            });
        });

        // -------------------------------------------------
        // 🏪 STORE MANAGER ROUTES
        // -------------------------------------------------
        Route::middleware(['role:store_manager'])->prefix('store-manager')->group(function () {

            // Dashboard
            Route::prefix('dashboard')->group(function () {
                Route::get('/',             [DashboardController::class, 'dashboard']);
                Route::get('/stats',        [DashboardController::class, 'stats']);
                Route::get('/order-stats',  [DashboardController::class, 'orderStat']);
                Route::get('/most-sold-products', [DashboardController::class, 'mostSoldProducts']);
                Route::get('/most-sold-brands',   [DashboardController::class, 'mostSoldBrands']);
                Route::get('/stocks',       [DashboardController::class, 'stock']);
                Route::get('/stock-alert',  [DashboardController::class, 'stockAlert']);
            });

            // Orders (view & manage)
            Route::prefix('orders')->group(function () {
                // Route::get('/{status}',       [OrderController::class, 'filterByStatus']);
                Route::get('/histories',      [PosController::class, 'orderHistory']);
                Route::get('/detail/{id}',    [PosController::class, 'orderDetails']);
                Route::patch('/confirm/{id}', [UpdateStatusController::class, 'markAsConfirmed']);
                Route::patch('/ship/{id}',    [UpdateStatusController::class, 'markAsShipping']);
                Route::patch('/deliver/{id}', [UpdateStatusController::class, 'markAsDelivered']);
                Route::patch('/complete/{id}',[UpdateStatusController::class, 'markAsCompleted']);
                Route::put('/payment/{id}',   [UpdateStatusController::class, 'updatePaymentStatus']);
            });

            // Returns
            Route::prefix('returns')->group(function () {
                Route::get('/',               [ReturnOrderController::class, 'getAll']);
                Route::get('/detail/{id}',    [PosController::class, 'returnDetails']);
                Route::patch('/approve/{id}', [ReturnOrderController::class, 'approve']);
                Route::patch('/reject/{id}',  [ReturnOrderController::class, 'reject']);
                Route::patch('/complete/{id}',[ReturnOrderController::class, 'complete']);
            });

            // Products (full CRUD)
            Route::prefix('products')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'index']);
                Route::get('/{id}',    [ProductPermissionController::class, 'productId']);
                Route::post('/',       [ProductController::class, 'store']);
                Route::post('/{id}',   [ProductController::class, 'update']);
                Route::delete('/{id}', [ProductController::class, 'destroy']);
            });

            // Product Variants (full CRUD)
            Route::prefix('product-variants')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'variant']);
                Route::get('/{id}',    [ProductPermissionController::class, 'variantId']);
                Route::post('/',       [ProductVariantController::class, 'store']);
                Route::post('/{id}',   [ProductVariantController::class, 'update']);
                Route::delete('/{id}', [ProductVariantController::class, 'destroy']);
            });

            // Categories (full CRUD)
            Route::prefix('categories')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'category']);
                Route::get('/{id}',    [ProductPermissionController::class, 'categoryId']);
                Route::post('/',       [CategoryController::class, 'store']);
                Route::post('/{id}',   [CategoryController::class, 'update']);
                Route::delete('/{id}', [CategoryController::class, 'destroy']);
            });

            // Brands (full CRUD)
            Route::prefix('brands')->group(function () {
                Route::get('/',        [ProductPermissionController::class, 'brand']);
                Route::get('/{id}',    [ProductPermissionController::class, 'brandId']);
                Route::post('/',       [BrandController::class, 'store']);
                Route::post('/{id}',   [BrandController::class, 'update']);
                Route::delete('/{id}', [BrandController::class, 'destroy']);
            });

            // Discounts (full CRUD)
            Route::prefix('discounts')->group(function () {
                Route::get('/',        [DiscountController::class, 'getAll']);
                Route::get('/{id}',    [DiscountController::class, 'show']);
                Route::post('/',       [DiscountController::class, 'store']);
                Route::put('/{id}',    [DiscountController::class, 'update']);
                Route::delete('/{id}', [DiscountController::class, 'destroy']);
            });

            // Coupons (full CRUD)
            Route::prefix('coupons')->group(function () {
                Route::get('/',        [CouponController::class, 'index']);
                Route::get('/{id}',    [CouponController::class, 'getById']);
                Route::post('/',       [CouponController::class, 'store']);
                Route::post('/{id}',   [CouponController::class, 'update']);
                Route::delete('/{id}', [CouponController::class, 'destroy']);
            });

            // Product Damages
            Route::prefix('product-damages')->group(function () {
                Route::get('/',        [ProductDamageController::class, 'index']);
                Route::post('/',       [ProductDamageController::class, 'store']);
                Route::post('/{id}',   [ProductDamageController::class, 'update']);
                Route::delete('/{id}', [ProductDamageController::class, 'destroy']);
            });

            // Notifications
            Route::prefix('notifications')->group(function () {
                Route::get('/',           [NotificationController::class, 'notifications']);
                Route::get('/{id}',       [NotificationController::class, 'getById']);
                Route::post('/seen/{id}', [NotificationController::class, 'markAsSeen']);
            });

            // Reports (view only)
            Route::get('/sale-reports',    [DashboardController::class, 'salesReport']);
            Route::get('/product-reports', [DashboardController::class, 'productsReport']);
        });

        // -------------------------------------------------
        // 👤 STAFF ROUTES
        // -------------------------------------------------
        Route::middleware(['role:staff'])->prefix('staff')->group(function () {

            // Dashboard (limited)
            Route::prefix('dashboard')->group(function () {
                Route::get('/',            [DashboardController::class, 'dashboard']);
                Route::get('/stats',       [DashboardController::class, 'stats']);
                Route::get('/order-stats', [DashboardController::class, 'orderStat']);
                Route::get('/stock-alert', [DashboardController::class, 'stockAlert']);
            });

            // Orders
            Route::prefix('orders')->group(function () {
                Route::get('/histories',      [PosController::class, 'orderHistory']);
                Route::get('/detail/{id}',    [PosController::class, 'orderDetails']);
                Route::patch('/confirm/{id}', [UpdateStatusController::class, 'markAsConfirmed']);
                Route::patch('/ship/{id}',    [UpdateStatusController::class, 'markAsShipping']);
                Route::patch('/deliver/{id}', [UpdateStatusController::class, 'markAsDelivered']);
                Route::patch('/complete/{id}',[UpdateStatusController::class, 'markAsCompleted']);
                Route::put('/payment/{id}',   [UpdateStatusController::class, 'updatePaymentStatus']);
            });

            // Returns
            Route::prefix('returns')->group(function () {
                Route::get('/',               [ReturnOrderController::class, 'getAll']);
                Route::get('/detail/{id}',    [PosController::class, 'returnDetails']);
                Route::patch('/approve/{id}', [ReturnOrderController::class, 'approve']);
                Route::patch('/reject/{id}',  [ReturnOrderController::class, 'reject']);
                Route::patch('/complete/{id}',[ReturnOrderController::class, 'complete']);
            });

            // Products (no delete)
            Route::prefix('products')->group(function () {
                Route::get('/',      [ProductPermissionController::class, 'index']);
                Route::get('/{id}',  [ProductPermissionController::class, 'productId']);
                Route::post('/',     [ProductController::class, 'store']);
                Route::post('/{id}', [ProductController::class, 'update']);
            });

            // Product Variants (no delete)
            Route::prefix('product-variants')->group(function () {
                Route::get('/',      [ProductPermissionController::class, 'variant']);
                Route::get('/{id}',  [ProductPermissionController::class, 'variantId']);
                Route::post('/',     [ProductVariantController::class, 'store']);
                Route::post('/{id}', [ProductVariantController::class, 'update']);
            });

            // Categories (no delete)
            Route::prefix('categories')->group(function () {
                Route::get('/',      [ProductPermissionController::class, 'category']);
                Route::get('/{id}',  [ProductPermissionController::class, 'categoryId']);
                Route::post('/',     [CategoryController::class, 'store']);
                Route::post('/{id}', [CategoryController::class, 'update']);
            });

            // Brands (no delete)
            Route::prefix('brands')->group(function () {
                Route::get('/',      [ProductPermissionController::class, 'brand']);
                Route::get('/{id}',  [ProductPermissionController::class, 'brandId']);
                Route::post('/',     [BrandController::class, 'store']);
                Route::post('/{id}', [BrandController::class, 'update']);
            });

            // Coupons (no delete)
            Route::prefix('coupons')->group(function () {
                Route::get('/',      [CouponController::class, 'index']);
                Route::get('/{id}',  [CouponController::class, 'getById']);
                Route::post('/',     [CouponController::class, 'store']);
                Route::post('/{id}', [CouponController::class, 'update']);
            });

            // Reviews (no delete)
            Route::prefix('reviews')->group(function () {
                Route::get('/',               [ReviewController::class, 'getAll']);
                Route::get('/{id}',           [ReviewController::class, 'getById']);
                Route::post('/reply/{id}',    [ReviewController::class, 'reply']);
                Route::put('/reply/{id}',     [ReviewController::class, 'updateReply']);
                Route::patch('/approve/{id}', [ReviewController::class, 'approved']);
            });

            // Notifications
            Route::prefix('notifications')->group(function () {
                Route::get('/',           [NotificationController::class, 'notifications']);
                Route::get('/{id}',       [NotificationController::class, 'getById']);
                Route::post('/seen/{id}', [NotificationController::class, 'markAsSeen']);
            });

            // Product Damages (no delete)
            Route::prefix('product-damages')->group(function () {
                Route::get('/',      [ProductDamageController::class, 'index']);
                Route::post('/',     [ProductDamageController::class, 'store']);
                Route::post('/{id}', [ProductDamageController::class, 'update']);
            });

            // Reports (view only)
            Route::get('/sale-reports',    [DashboardController::class, 'salesReport']);
            Route::get('/product-reports', [DashboardController::class, 'productsReport']);
        });

        // -------------------------------------------------
        // 🎧 CUSTOMER SUPPORT ROUTES
        // -------------------------------------------------
        Route::middleware(['role:customer_support'])->prefix('support')->group(function () {

            // Orders (view & status update only)
            Route::prefix('orders')->group(function () {
                Route::get('/histories',      [PosController::class, 'orderHistory']);
                Route::get('/detail/{id}',    [PosController::class, 'orderDetails']);
                Route::patch('/confirm/{id}', [UpdateStatusController::class, 'markAsConfirmed']);
                Route::patch('/ship/{id}', [UpdateStatusController::class, 'markAsShipping']);
                Route::patch('/deliver/{id}', [UpdateStatusController::class, 'markAsDelivered']);
                Route::patch('/complete/{id}',[UpdateStatusController::class, 'markAsCompleted']);
            });

            // Returns (view & approve/reject)
            Route::prefix('returns')->group(function () {
                Route::get('/',               [ReturnOrderController::class, 'getAll']);
                Route::get('/detail/{id}',    [PosController::class, 'returnDetails']);
                Route::patch('/approve/{id}', [ReturnOrderController::class, 'approve']);
                Route::patch('/reject/{id}',  [ReturnOrderController::class, 'reject']);
                Route::patch('/complete/{id}',[ReturnOrderController::class, 'complete']);
            });

            // Reviews (reply & approve only)
            Route::prefix('reviews')->group(function () {
                Route::get('/',               [ReviewController::class, 'getAll']);
                Route::get('/{id}',           [ReviewController::class, 'getById']);
                Route::post('/reply/{id}',    [ReviewController::class, 'reply']);
                Route::put('/reply/{id}',     [ReviewController::class, 'updateReply']);
                Route::patch('/approve/{id}', [ReviewController::class, 'approved']);
            });

            // Users (view only)
            Route::prefix('users')->group(function () {
                Route::get('/',      [UserController::class, 'getAllUser']);
                Route::get('/{id}',  [UserController::class, 'searchUser']);
            });

            // Notifications
            Route::prefix('notifications')->group(function () {
                Route::get('/',           [NotificationController::class, 'notifications']);
                Route::get('/{id}',       [NotificationController::class, 'getById']);
                Route::post('/seen/{id}', [NotificationController::class, 'markAsSeen']);
            });
        });

    }); // end auth:sanctum

}); // end v1