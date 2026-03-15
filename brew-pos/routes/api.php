<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TableController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Users (admin only)
    Route::middleware('role:admin')->group(function () {
        Route::get('/users', [AuthController::class, 'users']);
        Route::post('/users', [AuthController::class, 'storeUser']);
        Route::put('/users/{user}', [AuthController::class, 'updateUser']);
        Route::delete('/users/{user}', [AuthController::class, 'deleteUser']);
    });

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Products
    Route::apiResource('products', ProductController::class);

    // Tables
    Route::apiResource('tables', TableController::class);

    // Orders
    // NOTE: Static routes (/kitchen, /cancel) must come BEFORE {order} wildcard
    Route::get('/orders',                          [OrderController::class, 'index']);
    Route::post('/orders',                         [OrderController::class, 'store']);
    Route::get('/orders/{order}',                  [OrderController::class, 'show']);
    Route::patch('/orders/{order}/status',         [OrderController::class, 'updateStatus']);
    Route::patch('/orders/{order}/cancel',         [OrderController::class, 'cancel']);
    Route::delete('/orders/{order}',               [OrderController::class, 'destroy']);

    // Kitchen display (separate controller, separate prefix)
    Route::get('/kitchen/orders',                  [KitchenController::class, 'index']);
    Route::patch('/kitchen/orders/{order}/ready',  [KitchenController::class, 'markReady']);
    Route::patch('/kitchen/orders/{order}/complete', [KitchenController::class, 'markCompleted']);

    // Payments
    Route::post('/payments', [PaymentController::class, 'process']);

    // Inventory
    // NOTE: /inventory/logs must come BEFORE /inventory/{inventory} wildcard
    Route::get('/inventory',                          [InventoryController::class, 'index']);
    Route::get('/inventory/logs',                     [InventoryController::class, 'logs']);
    Route::patch('/inventory/{inventory}/adjust',     [InventoryController::class, 'adjust']);

    // Reports
    Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);
    Route::get('/reports/sales',     [ReportController::class, 'salesByDate']);

    // Settings
    Route::get('/settings',  [SettingsController::class, 'index']);
    Route::put('/settings',  [SettingsController::class, 'update']);
});
