<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FrequentPayeeController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransferController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Banco Ficticio - API Routes
| Frontend: React (separate repository)
| Auth: Bearer token via Laravel Sanctum
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (require valid Sanctum token)
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/password', [AuthController::class, 'changePassword']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::get('/profile/photo', [ProfileController::class, 'showPhoto']);
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);

    // Account
    Route::get('/account', [AccountController::class, 'index']);

    // Transfers
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::get('/transfers', [TransferController::class, 'index']);
    Route::get('/transfers/{id}', [TransferController::class, 'show']);

    // Frequent payees
    Route::apiResource('frequent-payees', FrequentPayeeController::class)->except(['show']);

    // Transaction history
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
});
