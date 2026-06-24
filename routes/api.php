<?php



use App\Http\Controllers\api\v1\Admin\CouponController;
use App\Http\Controllers\api\v1\Admin\NotificationController;
use App\Http\Controllers\api\v1\admin\PayWayController;
use App\Http\Controllers\api\v1\Admin\ProductDamageController;
use App\Http\Controllers\api\v1\Admin\ProductPermissionController;
use App\Http\Controllers\api\v1\Admin\StoreSettingController;
use App\Http\Controllers\api\v1\Customer\AddressController;
use App\Http\Controllers\api\v1\Admin\DashboardController;
use App\Http\Controllers\api\v1\Admin\PosController;
use App\Http\Controllers\api\v1\Admin\SettingController;
use App\Http\Controllers\api\v1\Auth\AuthController;
use App\Http\Controllers\api\v1\BrandController;
use App\Http\Controllers\api\v1\Export\ExportController;
use App\Http\Controllers\api\v1\Marketing\CarouselController;
use App\Http\Controllers\api\v1\Customer\CartController;
use App\Http\Controllers\api\v1\CategoryController;
use App\Http\Controllers\api\v1\Auth\GoogleAuthController;
use App\Http\Controllers\api\v1\Marketing\DiscountController;
use App\Http\Controllers\api\v1\Orders\OrderController;
use App\Http\Controllers\api\v1\ParentCategoryController;
use App\Http\Controllers\api\v1\Orders\PaymentController;
use App\Http\Controllers\api\v1\ProductController;
use App\Http\Controllers\api\v1\ProductVariantController;
use App\Http\Controllers\api\v1\Customer\ProfileController;
use App\Http\Controllers\api\v1\Orders\ReturnOrderController;
use App\Http\Controllers\api\v1\ReviewController;
use App\Http\Controllers\api\v1\Admin\UpdateStatusController;
use App\Http\Controllers\api\v1\Customer\UserController;
use App\Http\Controllers\api\v1\Customer\WishlistController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
Route::prefix('v1')->group(function () {
    Route::get('/orders/excel', [ExportController::class, 'exportOrderHistory']);
    Route::get('/products/excel', [ExportController::class, 'exportProducts']);
    Route::get('/sales-report/excel', [ExportController::class, 'exportSalesReport']);
    Route::get('/products-report/excel', [ExportController::class, 'exportProductsReport']);
    Route::get('/returns-history/excel', [ExportController::class, 'exportReturnHistoryExcel']);
    Route::get('/returns-history/pdf', [ExportController::class, 'exportReturnHistoryPdf']);
    Route::get('/products-report/pdf', [ExportController::class, 'exportProductsPdf']);
    Route::get('/sales-report/pdf', [ExportController::class, 'exportSalesReportPdf']);
    Route::get('/orders-history/pdf', [ExportController::class, 'orderHistoryPdf']);
    Route::get('/products-history/pdf', [ExportController::class, 'productHistoryPdf']);
    Route::get('/customers/excel', [ExportController::class, 'exportUsersExcel']);
    Route::get('/customers/pdf', [ExportController::class, 'exportUsersPDF']);
    Route::get('/admins/excel', [ExportController::class, 'exportAdminsExcel']);
    Route::get('/admins/pdf', [ExportController::class, 'exportAdminsPDF']);
    Route::get('/staffs/excel', [ExportController::class, 'exportStaffsExcel']);
    Route::get('/staffs/pdf', [ExportController::class, 'exportStaffsPDF']);
    Route::get('/stocks/excel', [ExportController::class, 'exportStock']);
    Route::get('/stocks/pdf', [ExportController::class, 'exportStockPDF']);
    Route::get('/variants/excel', [ExportController::class, 'exportVariantsExcel']);
    Route::get('/variants/pdf', [ExportController::class, 'exportVariantPDF']);
    Route::get('/product-damages/excel', [ExportController::class, 'exportProductDamageExcel']);
    Route::get('/product-damages/pdf', [ExportController::class, 'exportProductDamagePdf']);
    Route::get(
        '/orders/{orderId}/invoice',
        [ExportController::class, 'downloadInvoice']
    );

    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    });
    Route::get('/run-scheduler', function (Request $request) {
        if ($request->header('X-Scheduler-Token') !== env('SCHEDULER_TOKEN')) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        Artisan::call('discounts:check-expired');
        return response()->json(['message' => 'Scheduler ran successfully']);
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
        // Image search
        Route::get('/product-images/all', [ProductController::class, 'allImages']);
        Route::post('/visual-search', [ProductController::class, 'searchImage']);
        Route::post(
            '/text-search',
            [ProductController::class, 'searchByText']
        );
        Route::get('/products/{id}/reviews-summary', [ReviewController::class, 'productSummary']);
        // Carousels
        Route::get('/carousels', [CarouselController::class, 'index']);
        // popular products

        Route::get('/most-sold-products', [ProductController::class, 'mostSoldProducts']);
        Route::get('/products/new', [ProductController::class, 'newProducts']);
        // Products
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{id}', [ProductController::class, 'searchById']);
        Route::get('/products/slug/{slug}', [ProductController::class, 'searchBySlug']);
        // Top sale products
        Route::get('top-sales', [ProductController::class, 'topSales']);
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

        // Provinces
        Route::get('/provinces', [AddressController::class, 'provinces']);
        // Footer
        Route::get('/footers', [StoreSettingController::class, 'index']);
        Route::get('/footers/{id}', [StoreSettingController::class, 'show']);
        // Khqr payment

        Route::post('generate', [PaymentController::class, 'generate']);
        Route::post('verify', [PaymentController::class, 'verify']);
        Route::post('/payment/webhook', [PayWayController::class, 'webhook']);
    });

    // ------------------- PROTECTED -------------------
    Route::middleware(['auth:sanctum', 'check.status'])->group(function () {
        // Notify to users
        Route::post('/push-subscription', [UserController::class, 'savePushSubscription']);
        Route::delete('/push-subscription', [UserController::class, 'deletePushSubscription']);
        Route::get('/vapid-public-key', function () {
            return response()->json(['key' => env('VAPID_PUBLIC_KEY')]);
        });
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/read/{id}', [NotificationController::class, 'markAsRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
        Route::prefix('/payments')->group(function () {
            Route::post('/create', [PayWayController::class, 'create']);
            Route::post('/verify', [PayWayController::class, 'verify']);
        });
        // Coupon
        Route::post('/apply-coupon', [OrderController::class, 'apply']);
        Route::post('/clear-coupon', [OrderController::class, 'clear']);
        // Return Order
        Route::post('/return-orders', [ReturnOrderController::class, 'store']);
        Route::get('/return-orders', [ReturnOrderController::class, 'index']);
        Route::get('/return-orders/{id}', [ReturnOrderController::class, 'show']);
        Route::delete('/return-orders/{id}', [ReturnOrderController::class, 'destroy']);

        // Auth
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'me']);
        Route::put('/profile/update-name', [ProfileController::class, 'updateName']);
        Route::post('/profile/upload-image', [ProfileController::class, 'uploadProfileImage']);
        Route::get('/profile/images', [ProfileController::class, 'getProfileImage']);
        Route::put('/profile/change-password', [AuthController::class, 'changePassword']);
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
        // -----------------------------------------------
        // SUPER ADMIN ROUTES
        // -----------------------------------------------
        Route::middleware(['role:super-admin,admin'])->prefix('admin')->group(function () {

            // Orders — full management
            Route::get('/pos-orders', [PosController::class, 'orderHistory']);
            Route::get('/order-details/{id}', [PosController::class, 'orderDetails']);
            Route::patch('/orders/confirm/{id}', [UpdateStatusController::class, 'markAsConfirmed']);
            Route::patch('/orders/ship/{id}', [UpdateStatusController::class, 'markAsShipping']);
            Route::patch('/orders/deliver/{id}', [UpdateStatusController::class, 'markAsDelivered']);
            Route::put('/orders/payment/{id}', [UpdateStatusController::class, 'updatePaymentStatus']);
            Route::patch('/orders/complete/{id}', [UpdateStatusController::class, 'markAsCompleted']);

            // Return Orders — full management
            Route::get('/returns', [PosController::class, 'returnHistory']);
            Route::get('/return-details/{id}', [PosController::class, 'returnDetails']);
            Route::patch('/returns/approve/{id}', [ReturnOrderController::class, 'approve']);
            Route::patch('/returns/reject/{id}', [ReturnOrderController::class, 'reject']);
            Route::patch('/returns/complete/{id}', [ReturnOrderController::class, 'complete']);

            // Products — full CRUD
            Route::get('/products', [ProductPermissionController::class, 'index']);
            Route::get('/products/{id}', [ProductPermissionController::class, 'productId']);
            Route::post('/products', [ProductController::class, 'store']);
            Route::post('/products/{id}', [ProductController::class, 'update']);
            Route::delete('/products/{id}', [ProductController::class, 'destroy']);

            // Product Variants — full CRUD
            Route::get('/product-variants', [ProductPermissionController::class, 'variant']);
            Route::get('/product-variants/{id}', [ProductPermissionController::class, 'variantId']);
            Route::post('/product-variants', [ProductVariantController::class, 'store']);
            Route::post('/product-variants/{id}', [ProductVariantController::class, 'update']);
            Route::delete('/product-variants/{id}', [ProductVariantController::class, 'destroy']);

            // Categories — full CRUD
            Route::get('/categories', [ProductPermissionController::class, 'category']);
            Route::get('/categories/{id}', [ProductPermissionController::class, 'categoryId']);
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::post('/categories/{id}', [CategoryController::class, 'update']);
            Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);
            Route::post('/categories/upload-image/{id}', [CategoryController::class, 'uploadImageToCategory']);

            // Brands — full CRUD
            Route::get('/brands', [ProductPermissionController::class, 'brand']);
            Route::get('/brands/{id}', [ProductPermissionController::class, 'brandId']);
            Route::post('/brands', [BrandController::class, 'store']);
            Route::post('/brands/{id}', [BrandController::class, 'update']);
            Route::delete('/brands/{id}', [BrandController::class, 'destroy']);

            // Parent Categories — full CRUD
            Route::get('/parent-categories', [ProductPermissionController::class, 'parentCategory']);
            Route::get('/parent-categories/{id}', [ProductPermissionController::class, 'parentCategoryId']);
            Route::post('/parent-categories', [ParentCategoryController::class, 'store']);
            Route::put('/parent-categories/{id}', [ParentCategoryController::class, 'update']);
            Route::delete('/parent-categories/{id}', [ParentCategoryController::class, 'destroy']);

            // Discounts — full CRUD
            Route::get('/discounts', [DiscountController::class, 'getAll']);
            Route::get('/discounts/{id}', [DiscountController::class, 'show']);
            Route::post('/discounts', [DiscountController::class, 'store']);
            Route::put('/discounts/{id}', [DiscountController::class, 'update']);
            Route::delete('/discounts/{id}', [DiscountController::class, 'destroy']);
            Route::post('/discounts/add-products', [DiscountController::class, 'addProducts']);
            Route::post('/discounts/remove-product/{discountId}', [DiscountController::class, 'removeProduct']);
            Route::delete('/discounts/remove-product/{productId}', [DiscountController::class, 'removeProductId']);

            // Discount Categories — full CRUD
            Route::get('/discount-categories', [ProductPermissionController::class, 'discountCategory']);
            Route::get('/discount-categories/{id}', [ProductPermissionController::class, 'discountCategoryId']);

            // Carousels — full CRUD
            Route::get('/carousels', [ProductPermissionController::class, 'carousel']);
            Route::get('/carousels/{id}', [ProductPermissionController::class, 'carouselId']);
            Route::post('/carousels', [CarouselController::class, 'store']);
            Route::post('/carousels/{id}', [CarouselController::class, 'update']);
            Route::delete('/carousels/{id}', [CarouselController::class, 'destroy']);

            // Coupons — full CRUD
            Route::get('/coupons', [CouponController::class, 'index']);
            Route::get('/coupons/{id}', [CouponController::class, 'getById']);
            Route::post('/coupons', [CouponController::class, 'store']);
            Route::post('/coupons/{id}', [CouponController::class, 'update']);
            Route::delete('/coupons/{id}', [CouponController::class, 'destroy']);

            // Product Damages — full CRUD
            Route::get('/product-damages', [ProductDamageController::class, 'index']);
            Route::post('/product-damages', [ProductDamageController::class, 'store']);
            Route::post('/product-damages/{id}', [ProductDamageController::class, 'update']);
            Route::delete('/product-damages/{id}', [ProductDamageController::class, 'destroy']);

            // Notifications
            Route::get('/notifications', [NotificationController::class, 'notifications']);
            Route::get('/notifications/{id}', [NotificationController::class, 'getById']);
            Route::post('/notifications/{id}', [NotificationController::class, 'markAsSeen']);

            // Reviews — moderation
            Route::get('/reviews-reply', [ReviewController::class, 'getAll']);
            Route::get('/reviews-reply/{id}', [ReviewController::class, 'getById']);
            Route::post('/reviews-reply/{id}', [ReviewController::class, 'reply']);
            Route::put('/reviews-reply/{id}', [ReviewController::class, 'updateReply']);
            Route::patch('/reviews/approve/{id}', [ReviewController::class, 'approved']);
            Route::delete('/reviews-reply/{id}', [ReviewController::class, 'delete']);

            // Reports
            Route::get('/sale-reports', [DashboardController::class, 'salesReport']);
            Route::get('/product-reports', [DashboardController::class, 'productsReport']);

            // Dashboard
            Route::prefix('dashboard')->group(function () {
                Route::get('/', [DashboardController::class, 'dashboard']);
                Route::get('/stats', [DashboardController::class, 'stats']);
                Route::get('/order-stats', [DashboardController::class, 'orderStat']);
                Route::get('/most-sold-products', [DashboardController::class, 'mostSoldProducts']);
                Route::get('/stocks', [DashboardController::class, 'stock']);
                Route::get('/stock-alert', [DashboardController::class, 'stockAlert']);
            });

            // Users — management
            Route::get('/users', [UserController::class, 'getAllUser']);
            Route::get('/users/admins', [UserController::class, 'getAllAdmin']);
            Route::get('/users/staff', [UserController::class, 'getAllStaff']);
            Route::get('/user/orders', [UserController::class, 'userOrder']);
            Route::get('/users/admins/{id}', [UserController::class, 'searchAdmin']);
            Route::get('/users/{id}', [UserController::class, 'searchUser']);
            Route::get('/users/staff/{id}', [UserController::class, 'searchStaff']);
            Route::post('/users/{id}', [UserController::class, 'updateUser']);
            Route::delete('/users/{id}', [UserController::class, 'destroy']);

            // Settings
            Route::put('/settings/change-name', [SettingController::class, 'changeName']);
            Route::put('/settings/change-password', [SettingController::class, 'changePassword']);
            Route::post('/settings/change-avatar', [SettingController::class, 'changeProfileImage']);

            // SUPER ADMIN ONLY — danger zone
            Route::middleware(['role:super-admin'])->group(function () {
                Route::put('/users/role/{id}', [UserController::class, 'updateRoleUser']);
            });
            Route::prefix('footers')->group(function () {
                Route::get('/', [StoreSettingController::class, 'getAll']);
                Route::get('/{id}', [StoreSettingController::class, 'getById']);
                Route::post('/', [StoreSettingController::class, 'store']);
                Route::put('/bulk-update', [StoreSettingController::class, 'bulkUpdate']); // ← must be before /{id}
                Route::put('/{id}', [StoreSettingController::class, 'update']);
                Route::delete('/{id}', [StoreSettingController::class, 'destroy']);
            });
        });
    });



});