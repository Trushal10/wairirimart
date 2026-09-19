<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Client\CartController;
use App\Http\Controllers\Client\ShopController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\HomeVideoController;
use App\Http\Controllers\Client\AboutController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Client\ReviewController;
use App\Http\Controllers\Client\TrackingController;
use App\Http\Controllers\Admin\PaymentGatewayController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CourierPartnerController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DatabaseController as AdminDatabaseController;
use App\Http\Controllers\Client\BlogController as ClientBlogController;
// use App\Http\Controllers\Client\HomeController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Client\CheckoutController;
use App\Http\Controllers\Client\CouponController as ClientCouponController;
use App\Http\Controllers\Client\WishlistController;
use App\Http\Controllers\Client\ShoppingCartController;
use App\Http\Controllers\Client\CustomerAddressController;
use App\Http\Controllers\Client\HomeController as ClientHomeController;
use App\Http\Controllers\Client\OrderController as ClientOrderController;
use App\Http\Controllers\Client\ContactController as ClientContactController;
use App\Http\Controllers\Client\PaymentController as ClientPaymentController;
use App\Http\Controllers\Client\ProductController as ClientProductController;
use App\Http\Controllers\Client\ProfileController as ClientProfileController;
use App\Http\Controllers\Client\CategoryController as ClientCategoryController;

Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap');

Route::get('/', [ClientHomeController::class, 'index'])->name('client.home');
Route::get('shop', [ShopController::class, 'index'])->name('client.shop');
Route::get('categories', [ClientCategoryController::class, 'index'])->name('client.category');
Route::get('contact', [ClientContactController::class, 'index'])->name('client.contact');
Route::post('contact', [ClientContactController::class, 'save'])
    ->middleware('throttle:5,1')
    ->name('client.contact.save');
Route::get('about', [AboutController::class, 'index'])->name('client.about');
Route::get('blog', [ClientBlogController::class, 'index'])->name('client.blog.index');
Route::get('blog/{slug}', [ClientBlogController::class, 'show'])->name('client.blog.show');
Route::get('product-detail/{productSlug?}', [ClientProductController::class, 'index'])->name('client.product');
Route::get('checkout', [CheckoutController::class, 'index'])->name('client.checkout');
Route::get('shopping-cart', [ShoppingCartController::class, 'index'])->name('client.shoppingcart');
Route::post('update-cart-item', [ShoppingCartController::class, 'updateCheckOutItem'])->name('client.updateCartItem');
Route::post('remove-checkout-item', [ShoppingCartController::class, 'removeCheckOutItem'])->name('client.removeCheckOutItem');
Route::get('cart', [CartController::class, 'cartList'])->name('client.cartList');
Route::post('add-to-cart', [CartController::class, 'addToCart'])
    ->middleware('throttle:60,1')
    ->name('client.addToCart');
Route::post('quick-add', [CartController::class, 'quickAdd'])
    ->middleware('throttle:60,1')
    ->name('client.quickAdd');
Route::post('remove-cart-item', [CartController::class, 'removeCartItem'])
    ->middleware('throttle:60,1')
    ->name('client.removeCartItem');
Route::middleware('client_auth')->group(function () {
    Route::post('user-address-save', [CustomerAddressController::class, 'save'])->name('client.userAddress.save');
    Route::get('{id}/user-address-edit', [CustomerAddressController::class, 'edit'])
        ->whereNumber('id')->name('client.userAddress.edit');
    Route::put('{id}/user-address-update', [CustomerAddressController::class, 'update'])
        ->whereNumber('id')->name('client.userAddress.update');
    Route::delete('{id}/user-address-delete', [CustomerAddressController::class, 'delete'])
        ->whereNumber('id')->name('client.userAddress.delete');
    Route::post('/order', [ClientOrderController::class, 'save'])
        ->middleware('throttle:10,1')
        ->name('client.order.save');
    Route::get('/order/confirmation/{orderNo}', [ClientOrderController::class, 'confirmation'])
        ->whereAlphaNumeric('orderNo')
        ->name('client.order.confirmation');
    Route::get('/invoice/{orderNo}', [InvoiceController::class, 'customerInvoice'])
        ->whereAlphaNumeric('orderNo')
        ->name('client.invoice');
    Route::post('/razorpay/callback', [ClientPaymentController::class, 'razorpayCallback'])
        ->middleware('throttle:20,1')
        ->name('razorpay.callback');
});

Route::post('/webhooks/razorpay', [ClientPaymentController::class, 'razorpayWebhook'])
    ->middleware('throttle:120,1')
    ->name('razorpay.webhook');
Route::post('/webhooks/shiprocket', [ClientPaymentController::class, 'shiprocketWebhook'])
    ->middleware('throttle:120,1')
    ->name('shiprocket.webhook');

// Order tracking. The whole group is behind `tracking_enabled`: with the
// feature switched off (or no courier configured) these URLs 404 rather than
// rendering an empty tracker, so old emails and bookmarks fail cleanly.
Route::middleware('tracking_enabled')->group(function () {
    Route::get('/track', [TrackingController::class, 'form'])->name('client.track.form');
    Route::post('/track', [TrackingController::class, 'verify'])
        ->middleware('throttle:10,1')
        ->name('client.track.verify');
    Route::get('/track/{orderNo}', [TrackingController::class, 'show'])
        ->whereAlphaNumeric('orderNo')
        ->name('client.track.show');
    Route::get('/track/{orderNo}/live', [TrackingController::class, 'live'])
        ->whereAlphaNumeric('orderNo')
        ->middleware('throttle:120,1')
        ->name('client.track.live');
    Route::post('/track/{orderNo}/logout', [TrackingController::class, 'logout'])
        ->whereAlphaNumeric('orderNo')
        ->name('client.track.logout');
});

Route::get('/reviews', [ReviewController::class, 'getReviewsByProduct'])->name('client.products.reviews');
Route::post('/reviews', [ReviewController::class, 'store'])
    ->middleware(['client_auth', 'throttle:10,60'])
    ->name('client.reviews.store');

// Coupon routes (apply is session-based, no auth needed; remove should also be accessible)
Route::post('/apply-coupon', [ClientCouponController::class, 'apply'])
    ->middleware('throttle:20,1')
    ->name('client.coupon.apply');
Route::delete('/remove-coupon', [ClientCouponController::class, 'remove'])->name('client.coupon.remove');

// Wishlist routes
Route::get('/wishlist/ids', [WishlistController::class, 'ids'])->name('client.wishlist.ids');
Route::middleware('client_auth')->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('client.wishlist');
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle'])
        ->middleware('throttle:60,1')
        ->name('client.wishlist.toggle');
    Route::post('/wishlist/remove', [WishlistController::class, 'remove'])->name('client.wishlist.remove');
});

Route::middleware('client_auth')->group(function () {
    Route::get('profile', [ClientProfileController::class, 'index'])->name('client.profile');
    Route::put('profile', [ClientProfileController::class, 'updateProfile'])->name('client.profile.update');
    Route::put('profile-image', [ClientProfileController::class, 'updateProfileImage'])->name('client.profileImage.update');
    Route::put('update-password', [ClientProfileController::class, 'updatePassword'])->name('client.profile.password');

    // Cancelling is only ever allowed while the order is still pending; the
    // controller re-checks that under a row lock, since a prepaid order can be
    // confirmed by a webhook between the page rendering and this arriving.
    Route::post('/orders/{orderNo}/cancel', [ClientOrderController::class, 'cancel'])
        ->whereAlphaNumeric('orderNo')
        ->middleware('throttle:10,1')
        ->name('client.order.cancel');

    Route::prefix('returns')->name('client.returns.')->group(function () {
        Route::get('{orderNo}/new', [\App\Http\Controllers\Client\ReturnController::class, 'create'])->name('create');
        Route::post('{orderNo}', [\App\Http\Controllers\Client\ReturnController::class, 'store'])->name('store')
            ->middleware('throttle:10,1');
        Route::get('view/{returnNo}', [\App\Http\Controllers\Client\ReturnController::class, 'show'])->name('show');
        Route::post('view/{returnNo}/cancel', [\App\Http\Controllers\Client\ReturnController::class, 'cancel'])->name('cancel');
    });
});

require __DIR__ . '/client-auth.php';

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('dashboard', [HomeController::class, 'index'])->name('dashboard');

        // Design system reference — visible only in local/dev environments.
        if (app()->environment('local', 'development')) {
            Route::get('_playground', fn () => inertia('Admin/_Playground'))->name('_playground');
        }

        Route::get('profile', [ProfileController::class, 'index'])->name('profile');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::get('slider', [SliderController::class, 'index'])->name('sliders');
        Route::get('slider/create', [SliderController::class, 'create'])->name('slider.create');
        Route::post('slider/save', [SliderController::class, 'save'])->name('slider.save');
        Route::get('slider/{slider}/edit', [SliderController::class, 'edit'])->name('slider.edit');
        Route::put('slider/{slider}/update', [SliderController::class, 'update'])->name('slider.update');
        Route::delete('slider/delete/{slider}', [SliderController::class, 'delete'])->name('slider.delete');
        Route::prefix('slider/{slider}/media')->group(function () {
            Route::post('save', [SliderController::class, 'saveMedia'])->name('slider.media.save');
            Route::put('{media}/update', [SliderController::class, 'updateMedia'])->name('slider.media.update');
            Route::delete('{media}/delete', [SliderController::class, 'deleteMedia'])->name('slider.media.delete');
        });

        Route::get('home-videos', [HomeVideoController::class, 'index'])->name('home-videos');
        Route::post('home-videos', [HomeVideoController::class, 'store'])->name('home-videos.store');
        Route::put('home-videos/{homeVideo}', [HomeVideoController::class, 'update'])->name('home-videos.update');
        Route::delete('home-videos/{homeVideo}', [HomeVideoController::class, 'delete'])->name('home-videos.delete');

        Route::prefix('a_category')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('category');
            Route::get('/create', [CategoryController::class, 'create'])->name('category.create');
            Route::post('/create', [CategoryController::class, 'store'])->name('category.store');
            Route::get('{category}/edit/', [CategoryController::class, 'edit'])->name('category.edit');
            Route::put('{category}/update', [CategoryController::class, 'update'])->name('category.update');
            Route::delete('{category}/delete', [CategoryController::class, 'delete'])->name('category.delete');
        });

        Route::prefix('a_products')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('products');
            Route::get('/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/create', [ProductController::class, 'store'])->name('product.store');
            Route::get('{product}/edit/', [ProductController::class, 'edit'])->name('product.edit');
            Route::put('{product}/update', [ProductController::class, 'update'])->name('product.update');
            Route::delete('{product}/delete', [ProductController::class, 'delete'])->name('product.delete');
        });

        Route::prefix('a_contacts')->group(function () {
            Route::get('/', [ContactController::class, 'index'])->name('contacts');
            Route::delete('{contact}/delete', [ContactController::class, 'delete'])->name('contact.delete');
        });

        Route::prefix('a_orders')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('orders');
            Route::get('{order}/detail', [OrderController::class, 'detail'])->name('order.detail');
            Route::get('{order}/invoice', [InvoiceController::class, 'adminInvoice'])->name('order.invoice');
            Route::put('{id}/update', [OrderController::class, 'update'])->name('order.update');
            Route::put('orders/{order}/delivery', [OrderController::class, 'addDelivery'])
                ->name('orders.delivery.update');
            Route::post('{order}/refund', [OrderController::class, 'refund'])->name('order.refund');
            Route::get('{order}/serviceability', [OrderController::class, 'checkServiceability'])
                ->name('order.serviceability');
            Route::get('{order}/label', [OrderController::class, 'label'])->name('order.label');

            Route::prefix('shipment/{shipment}')->group(function () {
                Route::post('assign-awb', [OrderController::class, 'assignAwb'])->name('shipment.assign_awb');
                Route::post('pickup', [OrderController::class, 'requestPickup'])->name('shipment.pickup');
                Route::post('label', [OrderController::class, 'generateLabel'])->name('shipment.label');
                Route::post('sync', [OrderController::class, 'syncTracking'])->name('shipment.sync');
                Route::post('cancel', [OrderController::class, 'cancelShipment'])->name('shipment.cancel');
            });
        });
        Route::prefix('a_payment')->group(function () {
            Route::get('/', [PaymentController::class, 'index'])->name('payments');
            Route::delete('{payment}/delete', [PaymentController::class, 'delete'])->name('payment.delete');
        });
        Route::get('setting', [SettingController::class, 'edit'])->name('setting');
        Route::put('{setting}/update', [SettingController::class, 'update'])->name('setting.update');

        Route::prefix('returns')->name('returns.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\ReturnController::class, 'index'])->name('index');
            Route::get('{return}', [\App\Http\Controllers\Admin\ReturnController::class, 'show'])->name('show');
            Route::post('{return}/approve', [\App\Http\Controllers\Admin\ReturnController::class, 'approve'])->name('approve');
            Route::post('{return}/reject', [\App\Http\Controllers\Admin\ReturnController::class, 'reject'])->name('reject');
            Route::post('{return}/received', [\App\Http\Controllers\Admin\ReturnController::class, 'markReceived'])->name('received');
            Route::post('{return}/refunded', [\App\Http\Controllers\Admin\ReturnController::class, 'markRefunded'])->name('refunded');
        });

        // Reports module — Sales, Orders, Payments, Shipping, P&L
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', fn () => redirect()->route('admin.reports.sales'))->name('index');
            Route::get('sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('orders', [ReportController::class, 'orders'])->name('orders');
            Route::get('payments', [ReportController::class, 'payments'])->name('payments');
            Route::get('shipping', [ReportController::class, 'shipping'])->name('shipping');
            Route::get('pnl', [ReportController::class, 'pnl'])->name('pnl');

            // Explicit export endpoints (also reachable via ?format= on the page routes).
            Route::get('sales/export', [ReportController::class, 'exportOrders'])->name('sales.export');
            Route::get('orders/export', [ReportController::class, 'exportOrders'])->name('orders.export');
            Route::get('payments/export', [ReportController::class, 'exportPayments'])->name('payments.export');
            Route::get('shipping/export', [ReportController::class, 'exportShipments'])->name('shipping.export');
            Route::get('pnl/export', [ReportController::class, 'exportPnl'])->name('pnl.export');

            // Backwards-compat: the old *.csv URLs kept as aliases.
            Route::get('orders.csv', [ReportController::class, 'ordersCsvLegacy'])->name('orders.csv');
            Route::get('payments.csv', [ReportController::class, 'paymentsCsvLegacy'])->name('payments.csv');
        });

        Route::prefix('expenses')->name('expenses.')->group(function () {
            Route::get('/', [ExpenseController::class, 'index'])->name('index');
            Route::get('create', [ExpenseController::class, 'create'])->name('create');
            Route::post('/', [ExpenseController::class, 'store'])->name('store');
            Route::get('{expense}/edit', [ExpenseController::class, 'edit'])->name('edit');
            Route::put('{expense}', [ExpenseController::class, 'update'])->name('update');
            Route::delete('{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('a_coupons')->name('coupons.')->group(function () {
            Route::get('/', [CouponController::class, 'index'])->name('index');
            Route::get('/create', [CouponController::class, 'create'])->name('create');
            Route::post('/create', [CouponController::class, 'store'])->name('store');
            Route::get('{coupon}/edit', [CouponController::class, 'edit'])->name('edit');
            Route::put('{coupon}/update', [CouponController::class, 'update'])->name('update');
            Route::delete('{coupon}/delete', [CouponController::class, 'delete'])->name('delete');
            Route::post('{coupon}/toggle', [CouponController::class, 'toggle'])->name('toggle');
        });

        Route::prefix('a_blogs')->name('blogs.')->group(function () {
            Route::get('/', [BlogController::class, 'index'])->name('index');
            Route::get('/create', [BlogController::class, 'create'])->name('create');
            Route::post('/create', [BlogController::class, 'store'])->name('store');
            Route::get('{blog}/edit', [BlogController::class, 'edit'])->name('edit');
            Route::put('{blog}/update', [BlogController::class, 'update'])->name('update');
            Route::delete('{blog}/delete', [BlogController::class, 'delete'])->name('delete');
            Route::post('{blog}/toggle', [BlogController::class, 'toggle'])->name('toggle');
        });

        Route::prefix('a_customers')->name('customers.')->group(function () {
            Route::get('/', [AdminCustomerController::class, 'index'])->name('index');
            Route::get('{customer}', [AdminCustomerController::class, 'show'])->name('show');
            Route::post('{customer}/block', [AdminCustomerController::class, 'block'])->name('block');
            Route::post('{customer}/unblock', [AdminCustomerController::class, 'unblock'])->name('unblock');
            Route::delete('{customer}', [AdminCustomerController::class, 'delete'])->name('delete');
        });

        Route::prefix('settings/database')->name('database.')->group(function () {
            Route::get('/', [AdminDatabaseController::class, 'index'])->name('index');
            Route::get('/backup', [AdminDatabaseController::class, 'backup'])
                ->middleware('throttle:6,1')
                ->name('backup');
            Route::get('/backup/{filename}', [AdminDatabaseController::class, 'download'])
                ->where('filename', '[A-Za-z0-9._-]+\.sql')
                ->name('download');
            Route::post('/clear', [AdminDatabaseController::class, 'clear'])
                ->middleware('throttle:3,1')
                ->name('clear');
        });

        Route::prefix('settings/payment-gateways')->name('payment_gateways.')->group(function () {
            Route::get('/', [PaymentGatewayController::class, 'index'])->name('index');
            Route::get('{paymentGateway}/edit', [PaymentGatewayController::class, 'edit'])->name('edit');
            Route::put('{paymentGateway}', [PaymentGatewayController::class, 'update'])->name('update');
            Route::post('{paymentGateway}/toggle', [PaymentGatewayController::class, 'toggle'])->name('toggle');
            Route::post('{paymentGateway}/default', [PaymentGatewayController::class, 'setDefault'])->name('default');
            Route::post('{paymentGateway}/test', [PaymentGatewayController::class, 'testConnection'])->name('test');
            // Returns one stored secret in the clear; audited and rate limited.
            Route::post('{paymentGateway}/reveal', [PaymentGatewayController::class, 'revealCredential'])
                ->middleware('throttle:10,1')
                ->name('reveal');
        });

        Route::prefix('settings/couriers')->name('couriers.')->group(function () {
            Route::get('/', [CourierPartnerController::class, 'index'])->name('index');
            Route::get('{courier}/edit', [CourierPartnerController::class, 'edit'])->name('edit');
            Route::put('{courier}', [CourierPartnerController::class, 'update'])->name('update');
            Route::post('{courier}/toggle', [CourierPartnerController::class, 'toggle'])->name('toggle');
            Route::post('{courier}/default', [CourierPartnerController::class, 'setDefault'])->name('default');
            Route::post('{courier}/test', [CourierPartnerController::class, 'testConnection'])->name('test');
            // Returns one stored secret in the clear; audited and rate limited.
            Route::post('{courier}/reveal', [CourierPartnerController::class, 'revealCredential'])
                ->middleware('throttle:10,1')
                ->name('reveal');
        });

        // Universal search endpoint powering the admin Command Palette (⌘K).
        Route::get('search', \App\Http\Controllers\Admin\SearchController::class)
            ->name('search')
            ->middleware('throttle:60,1');
    });
    require __DIR__ . '/auth.php';
});
