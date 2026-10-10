<?php

use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    Route::prefix('products')->name('products.')->group(function () {
        Route::get('dummy', [ProductController::class, 'dummy'])->name('dummy');
    });

    Route::prefix('branches/{branch}')->name('branches.')->group(function () {
        Route::get('/', [BranchController::class, 'show'])->name('show');
        Route::get('menu', [MenuController::class, 'index'])->name('menu');
    });
});
