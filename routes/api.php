<?php

use App\Http\Controllers\Api\AccessCheckController;
use App\Http\Controllers\Api\AdminMaster\AdminMasterDashboardController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Cashier\CashierDashboardController;
use App\Http\Controllers\Api\Cashier\CashierReportController;
use App\Http\Controllers\Api\Cashier\PosController;
use App\Http\Controllers\Api\Cashier\ReturnController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\InventoryReportController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\MasterData\BrandController;
use App\Http\Controllers\Api\MasterData\CategoryController;
use App\Http\Controllers\Api\MasterData\ProductTypeController;
use App\Http\Controllers\Api\MasterData\UnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductStockController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\Store\StoreCartController;
use App\Http\Controllers\Api\Store\StoreInfoController;
use App\Http\Controllers\Api\Store\StoreOrderController;
use App\Http\Controllers\Api\Store\StoreProductController;
use App\Http\Controllers\Api\Superadmin\MonitorController;
use App\Http\Controllers\Api\Superadmin\ReportController;
use App\Http\Controllers\Api\Superadmin\SettingsController;
use App\Http\Controllers\Api\Superadmin\SuperadminDashboardController;
use App\Http\Controllers\Api\Superadmin\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('store')->middleware('throttle:store')->group(function () {
    Route::get('info', [StoreInfoController::class, 'info']);
    Route::get('filters', [StoreInfoController::class, 'filters']);
    Route::get('products', [StoreProductController::class, 'index']);
    Route::get('products/{id}', [StoreProductController::class, 'show'])->whereNumber('id');
    Route::post('cart/validate', [StoreCartController::class, 'validateCart']);
    Route::post('orders', [StoreOrderController::class, 'store'])->middleware('throttle:store-order');
    Route::get('orders/{orderNumber}', [StoreOrderController::class, 'show'])
        ->where('orderNumber', '[A-Za-z0-9\-]{6,30}')
        ->middleware('throttle:store-lookup');
});

Route::prefix('auth')->group(function () {
    Route::get('csrf', [AuthController::class, 'csrf']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:20,1');

    Route::middleware(['auth', 'active'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth', 'active', 'throttle:api'])->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'changePassword'])->middleware('throttle:6,1');

    Route::middleware('role:superadmin,admin_master')->group(function () {
        Route::get('lookups', LookupController::class);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show'])->whereNumber('product');
        Route::get('products/{product}/stock-movements', [ProductStockController::class, 'movements'])->whereNumber('product');
        Route::get('inventory/summary', [InventoryController::class, 'summary']);
        Route::get('inventory/movements', [InventoryController::class, 'movements']);
        Route::get('reports/inventory', InventoryReportController::class);
        Route::get('reports/inventory/export', [ReportExportController::class, 'inventory'])->middleware('throttle:12,1');
    });

    Route::middleware('role:admin_master')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update'])->whereNumber('product');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->whereNumber('product');
        Route::post('products/{product}/stock-adjustments', [ProductStockController::class, 'adjust'])->whereNumber('product');

        $masters = [
            'categories' => CategoryController::class,
            'product-types' => ProductTypeController::class,
            'brands' => BrandController::class,
            'units' => UnitController::class,
        ];

        foreach ($masters as $uri => $controller) {
            Route::get($uri, [$controller, 'index']);
            Route::post($uri, [$controller, 'store']);
            Route::put($uri.'/{id}', [$controller, 'update'])->whereNumber('id');
            Route::delete($uri.'/{id}', [$controller, 'destroy'])->whereNumber('id');
        }
    });

    Route::prefix('superadmin')->middleware('role:superadmin')->group(function () {
        Route::get('access-check', [AccessCheckController::class, 'superadmin']);
        Route::get('dashboard', SuperadminDashboardController::class);
    });

    Route::middleware('role:superadmin')->group(function () {
        Route::get('lookups/cashiers', [UserManagementController::class, 'cashierOptions']);

        Route::get('users', [UserManagementController::class, 'index']);
        Route::post('users', [UserManagementController::class, 'store']);
        Route::get('users/{user}', [UserManagementController::class, 'show'])->whereNumber('user');
        Route::put('users/{user}', [UserManagementController::class, 'update'])->whereNumber('user');
        Route::delete('users/{user}', [UserManagementController::class, 'destroy'])->whereNumber('user');
        Route::post('users/{user}/toggle', [UserManagementController::class, 'toggle'])->whereNumber('user');
        Route::post('users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])
            ->whereNumber('user')
            ->middleware('throttle:10,1');

        Route::get('reports/revenue', [ReportController::class, 'revenue']);
        Route::get('reports/revenue/transactions', [ReportController::class, 'revenueTransactions']);
        Route::get('reports/revenue/export', [ReportExportController::class, 'revenue'])->middleware('throttle:12,1');
        Route::get('reports/cashiers', [ReportController::class, 'cashiers']);
        Route::get('reports/cashiers/activities', [ReportController::class, 'cashierActivities']);
        Route::get('reports/cashiers/export', [ReportExportController::class, 'cashiers'])->middleware('throttle:12,1');

        Route::get('monitor/orders', [MonitorController::class, 'index']);
        Route::get('monitor/orders/{order}', [MonitorController::class, 'show'])->whereNumber('order');

        Route::get('settings/company', [SettingsController::class, 'company']);
        Route::post('settings/company', [SettingsController::class, 'updateCompany']);
        Route::get('settings/colors', [SettingsController::class, 'colors']);
        Route::put('settings/colors', [SettingsController::class, 'updateColors']);
        Route::post('settings/colors/reset', [SettingsController::class, 'resetColors']);
    });

    Route::prefix('kasir')->middleware('role:kasir')->group(function () {
        Route::get('access-check', [AccessCheckController::class, 'cashier']);
        Route::get('dashboard', CashierDashboardController::class);

        Route::get('orders/counts', [PosController::class, 'counts']);
        Route::post('orders/lookup', [PosController::class, 'lookup'])->middleware('throttle:cashier-lookup');
        Route::get('orders', [PosController::class, 'index']);
        Route::get('orders/{order}', [PosController::class, 'show'])->whereNumber('order');
        Route::post('orders/{order}/approve', [PosController::class, 'approve'])->whereNumber('order');
        Route::post('orders/{order}/complete', [PosController::class, 'complete'])->whereNumber('order');
        Route::post('orders/{order}/cancel', [PosController::class, 'cancel'])->whereNumber('order');
        Route::post('orders/{order}/returns', [ReturnController::class, 'store'])->whereNumber('order');

        Route::get('returns', [ReturnController::class, 'index']);
        Route::get('returns/{orderReturn}', [ReturnController::class, 'show'])->whereNumber('orderReturn');

        Route::get('reports/summary', [CashierReportController::class, 'summary']);
        Route::get('reports/transactions', [CashierReportController::class, 'transactions']);
        Route::get('reports/export', [ReportExportController::class, 'cashierSelf'])->middleware('throttle:12,1');
    });

    Route::prefix('admin-master')->middleware('role:admin_master')->group(function () {
        Route::get('access-check', [AccessCheckController::class, 'adminMaster']);
        Route::get('dashboard', AdminMasterDashboardController::class);
    });
});
