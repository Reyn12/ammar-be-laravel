<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\Staff\KitchenController;
use App\Http\Controllers\Api\V1\Staff\ShiftController;
use App\Http\Controllers\Api\V1\Staff\StaffOrderController;
use App\Http\Controllers\Api\V1\Staff\StreamController;
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

    Route::get('tables/{token}', [TableController::class, 'show'])->name('tables.show');

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
