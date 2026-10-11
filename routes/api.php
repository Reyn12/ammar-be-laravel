<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\Staff\DeviceTokenController;
use App\Http\Controllers\Api\V1\Staff\KitchenController;
use App\Http\Controllers\Api\V1\Staff\ShiftController;
use App\Http\Controllers\Api\V1\Staff\StaffCategoryController;
use App\Http\Controllers\Api\V1\Staff\StaffOrderController;
use App\Http\Controllers\Api\V1\Staff\StaffProductController;
use App\Http\Controllers\Api\V1\Staff\StaffTableController;
use App\Http\Controllers\Api\V1\Staff\StaffUserController;
use App\Http\Controllers\Api\V1\Staff\StreamController;
use App\Http\Controllers\Api\V1\Staff\UploadController;
use App\Http\Controllers\Api\V1\TableController;
use App\Http\Controllers\Api\V1\TripayCallbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    // Staf (owner, kasir, koki) - aplikasi Flutter
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
        });
    });

    Route::prefix('products')->name('products.')->group(function () {
        Route::get('dummy', [ProductController::class, 'dummy'])->name('dummy');
    });

    Route::prefix('branches/{branch}')->name('branches.')->group(function () {
        Route::get('/', [BranchController::class, 'show'])->name('show');
        Route::get('menu', [MenuController::class, 'index'])->name('menu');
    });

    // Kasir & owner
    Route::middleware(['auth:sanctum', 'role:cashier,owner'])->group(function () {
        Route::get('orders', [StaffOrderController::class, 'index'])->name('staff.orders.index');
        Route::get('orders/{order}', [StaffOrderController::class, 'show'])->whereNumber('order')->name('staff.orders.show');
        Route::post('orders/{order}/confirm-cash', [StaffOrderController::class, 'confirmCash'])->whereNumber('order')->name('staff.orders.confirm-cash');
    });

    // Kelola menu (kasir & owner)
    Route::middleware(['auth:sanctum', 'role:cashier,owner'])->group(function () {
        Route::get('categories', [StaffProductController::class, 'categories'])->name('staff.categories');
        Route::get('products', [StaffProductController::class, 'index'])->name('staff.products.index');
        Route::post('products', [StaffProductController::class, 'store'])->name('staff.products.store');
        Route::put('products/{product}', [StaffProductController::class, 'update'])->whereNumber('product')->name('staff.products.update');
        Route::delete('products/{product}', [StaffProductController::class, 'destroy'])->whereNumber('product')->name('staff.products.destroy');
        Route::post('uploads/product-image', [UploadController::class, 'productImage'])->name('staff.uploads.product-image');
    });

    // Owner: akun staf, kategori menu, dan meja
    Route::middleware(['auth:sanctum', 'role:owner'])->group(function () {
        Route::get('users', [StaffUserController::class, 'index'])->name('staff.users.index');
        Route::post('users', [StaffUserController::class, 'store'])->name('staff.users.store');
        Route::put('users/{user}', [StaffUserController::class, 'update'])->whereNumber('user')->name('staff.users.update');
        Route::delete('users/{user}', [StaffUserController::class, 'destroy'])->whereNumber('user')->name('staff.users.destroy');

        Route::post('categories', [StaffCategoryController::class, 'store'])->name('staff.categories.store');
        Route::put('categories/{category}', [StaffCategoryController::class, 'update'])->whereNumber('category')->name('staff.categories.update');
        Route::delete('categories/{category}', [StaffCategoryController::class, 'destroy'])->whereNumber('category')->name('staff.categories.destroy');

        Route::get('tables', [StaffTableController::class, 'index'])->name('staff.tables.index');
        Route::post('tables', [StaffTableController::class, 'store'])->name('staff.tables.store');
        Route::put('tables/{table}', [StaffTableController::class, 'update'])->whereNumber('table')->name('staff.tables.update');
        Route::delete('tables/{table}', [StaffTableController::class, 'destroy'])->whereNumber('table')->name('staff.tables.destroy');
        Route::post('tables/{table}/regenerate-qr', [StaffTableController::class, 'regenerateQr'])->whereNumber('table')->name('staff.tables.regenerate-qr');
    });

    // Dapur (koki & owner)
    Route::middleware(['auth:sanctum', 'role:kitchen,owner'])->prefix('kitchen')->name('kitchen.')->group(function () {
        Route::get('orders', [KitchenController::class, 'orders'])->name('orders');
        Route::get('batches', [KitchenController::class, 'batches'])->name('batches');
        Route::post('batches/{batch}/process', [KitchenController::class, 'processBatch'])->whereNumber('batch')->name('batches.process');
    });
    Route::patch('order-items/{orderItem}/status', [KitchenController::class, 'updateItemStatus'])
        ->middleware(['auth:sanctum', 'role:kitchen,owner'])->whereNumber('orderItem')->name('order-items.status');

    // Realtime (SSE) untuk semua staf
    Route::get('stream/orders', [StreamController::class, 'orders'])
        ->middleware(['auth:sanctum', 'role:cashier,kitchen,owner'])->name('stream.orders');

    // Token FCM perangkat untuk push notifikasi pesanan masuk
    Route::middleware(['auth:sanctum', 'role:cashier,kitchen,owner'])->prefix('devices')->name('devices.')->group(function () {
        Route::post('/', [DeviceTokenController::class, 'store'])->name('store');
        Route::delete('/', [DeviceTokenController::class, 'destroy'])->name('destroy');
        Route::post('test', [DeviceTokenController::class, 'test'])->middleware('throttle:6,1')->name('test');
    });

    Route::prefix('shifts')->name('shifts.')->middleware('auth:sanctum')->group(function () {
        Route::middleware('role:cashier')->group(function () {
            Route::get('active', [ShiftController::class, 'active'])->name('active');
            Route::post('open', [ShiftController::class, 'open'])->name('open');
            Route::post('{shift}/close', [ShiftController::class, 'close'])->whereNumber('shift')->name('close');
        });

        Route::middleware('role:owner')->group(function () {
            Route::get('/', [ShiftController::class, 'index'])->name('index');
            Route::post('{shift}/force-close', [ShiftController::class, 'forceClose'])->whereNumber('shift')->name('force-close');
        });
    });

    // Token QR meja panjang (32 karakter); batasan ini mencegah bentrok dengan rute staf /tables/{id}
    Route::get('tables/{token}', [TableController::class, 'show'])->where('token', '[A-Za-z0-9]{20,}')->name('tables.show');

    Route::prefix('orders')->name('orders.')->group(function () {
        Route::post('/', [OrderController::class, 'store'])->middleware('customer.auth:optional')->name('store');
        Route::get('{code}', [OrderController::class, 'show'])->where('code', 'AMR-[0-9]{8}-[0-9]+')->name('show');
    });

    Route::middleware('customer.auth')->prefix('me')->name('me.')->group(function () {
        Route::get('/', [CustomerController::class, 'me'])->name('show');
        Route::get('orders', [CustomerController::class, 'orders'])->name('orders');
    });

    Route::post('payments/tripay/callback', TripayCallbackController::class)->name('payments.tripay.callback');
});
