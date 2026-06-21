<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\DistributorController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\DailySettlementReportController;

Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->name('auth.change-password');

    Route::get('/orders/statistics', [OrderController::class, 'statistics'])->name('orders.statistics');
    Route::get('/payments/statistics', [PaymentController::class, 'statistics'])->name('payments.statistics');
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock'])->name('inventory.low-stock');

    Route::middleware('user_type:platform')->group(function () {
        Route::apiResource('suppliers', SupplierController::class);
        Route::post('/suppliers/{supplier}/approve', [SupplierController::class, 'approve'])->name('suppliers.approve');
        Route::post('/suppliers/{supplier}/reject', [SupplierController::class, 'reject'])->name('suppliers.reject');

        Route::apiResource('distributors', DistributorController::class);
        Route::post('/distributors/{distributor}/approve', [DistributorController::class, 'approve'])->name('distributors.approve');
        Route::post('/distributors/{distributor}/reject', [DistributorController::class, 'reject'])->name('distributors.reject');

        Route::post('/products/{product}/toggle-sale', [ProductController::class, 'toggleOnSale'])->name('products.toggle-sale');
        Route::post('/products/{product}/update-stock', [ProductController::class, 'updateStock'])->name('products.update-stock');

        Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('/orders/{order}/process', [OrderController::class, 'process'])->name('orders.process');
        Route::post('/orders/{order}/deliver', [OrderController::class, 'deliver'])->name('orders.deliver');
        Route::post('/orders/{order}/complete', [OrderController::class, 'complete'])->name('orders.complete');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

        Route::post('/inventory/{inventory}/adjust', [InventoryController::class, 'adjust'])->name('inventory.adjust');

        Route::get('/reports/daily-settlement', [ReportController::class, 'dailySettlement'])->name('reports.daily-settlement');
        Route::get('/reports/daily-settlement/export', [ReportController::class, 'exportDailySettlement'])->name('reports.daily-settlement.export');

        Route::get('/daily-settlement-reports/summary', [DailySettlementReportController::class, 'summary'])->name('daily-settlement-reports.summary');
        Route::get('/daily-settlement-reports/export', [DailySettlementReportController::class, 'export'])->name('daily-settlement-reports.export');
        Route::post('/daily-settlement-reports/generate-batch', [DailySettlementReportController::class, 'generateBatch'])->name('daily-settlement-reports.generate-batch');
        Route::post('/daily-settlement-reports/{dailySettlementReport}/regenerate', [DailySettlementReportController::class, 'regenerate'])->name('daily-settlement-reports.regenerate');
        Route::apiResource('daily-settlement-reports', DailySettlementReportController::class);
    });

    Route::middleware('user_type:platform,supplier')->group(function () {
        Route::apiResource('products', ProductController::class)->except(['create', 'edit']);
        Route::apiResource('inventory', InventoryController::class)->except(['create', 'edit']);

        Route::post('/orders/{order}/ship', [OrderController::class, 'ship'])->name('orders.ship');
    });

    Route::middleware('user_type:platform,distributor')->group(function () {
        Route::apiResource('orders', OrderController::class)->except(['create', 'edit']);
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    });

    Route::middleware('user_type:platform')->group(function () {
        Route::apiResource('payments', PaymentController::class)->except(['create', 'edit']);
    });

    Route::middleware('user_type:platform,supplier,distributor')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    });
});
