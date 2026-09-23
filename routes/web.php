<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryQualityController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuditLogController;

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Application Routes
Route::middleware(['auth', \App\Http\Middleware\InactivityTimeout::class])->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [AuthController::class, 'showProfile'])->name('profile');
    Route::post('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');

    // Inventory Routes
    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
        Route::get('/bulk-stock', [ProductController::class, 'bulkStockIndex'])->name('bulk_stock');
        Route::post('/bulk-stock', [ProductController::class, 'bulkStockStore'])->name('bulk_stock.store');
        Route::get('/bulk-prices', [ProductController::class, 'bulkPricesIndex'])->name('bulk_prices');
        Route::post('/bulk-prices', [ProductController::class, 'bulkPricesUpdate'])->name('bulk_prices.update');
        Route::get('/low-stock', [ProductController::class, 'lowStock'])->name('low_stock');
        Route::get('/analyze', [ProductController::class, 'analyzeStock'])->name('analyze');
    });

    // Product Attributes (Categories & Qualities)
    Route::prefix('attributes')->name('attributes.')->middleware(\App\Http\Middleware\CheckRole::class . ':admin')->group(function () {
        Route::get('/', [CategoryQualityController::class, 'index'])->name('index');
        Route::post('/category', [CategoryQualityController::class, 'storeCategory'])->name('category.store');
        Route::delete('/category/{category}', [CategoryQualityController::class, 'destroyCategory'])->name('category.destroy');
        Route::post('/quality', [CategoryQualityController::class, 'storeQuality'])->name('quality.store');
        Route::delete('/quality/{quality}', [CategoryQualityController::class, 'destroyQuality'])->name('quality.destroy');
    });

    // Sales POS & History
    Route::prefix('sales')->name('sales.')->group(function () {
        Route::get('/pos', [SalesController::class, 'create'])->name('pos');
        Route::get('/search-products', [SalesController::class, 'searchProducts'])->name('search');
        Route::post('/checkout', [SalesController::class, 'checkout'])->name('checkout');
        Route::get('/receipt/{id}', [SalesController::class, 'showReceipt'])->name('receipt');
        Route::get('/history', [SalesController::class, 'history'])->name('history');
    });

    // Customers & Debts
    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('/', [CustomerController::class, 'store'])->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])->name('show');
        Route::post('/{customer}/payments', [CustomerController::class, 'storePayment'])->name('payments.store');
    });

    // Repairs Management
    Route::prefix('repairs')->name('repairs.')->group(function () {
        Route::get('/', [RepairController::class, 'index'])->name('index');
        Route::post('/', [RepairController::class, 'store'])->name('store');
        Route::get('/{repair}', [RepairController::class, 'show'])->name('show');
        Route::patch('/{repair}/status', [RepairController::class, 'updateStatus'])->name('status');
    });

    // Returns Management
    Route::prefix('returns')->name('returns.')->group(function () {
        Route::get('/', [ReturnController::class, 'index'])->name('index');
        Route::post('/', [ReturnController::class, 'store'])->name('store');
        Route::patch('/{returnItem}/status', [ReturnController::class, 'updateStatus'])->name('status');
    });

    // Expenses Management (Admin)
    Route::prefix('expenses')->name('expenses.')->middleware(\App\Http\Middleware\CheckRole::class . ':admin')->group(function () {
        Route::get('/', [ExpenseController::class, 'index'])->name('index');
        Route::post('/', [ExpenseController::class, 'store'])->name('store');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
    });

    // Reports (Admin)
    Route::prefix('reports')->name('reports.')->middleware(\App\Http\Middleware\CheckRole::class . ':admin')->group(function () {
        Route::get('/closing', [ReportController::class, 'closingReport'])->name('closing');
    });

    // User Management (Admin)
    Route::prefix('users')->name('users.')->middleware(\App\Http\Middleware\CheckRole::class . ':admin')->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

    // Audit Log (Admin)
    Route::get('/audit-log', [AuditLogController::class, 'index'])->name('audit.index')->middleware(\App\Http\Middleware\CheckRole::class . ':admin');
});
