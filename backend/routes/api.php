<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:guest-api')->group(function () {
    Route::post('/guest/status', [GuestController::class, 'status']);
    Route::post('/guest/generate', [GuestController::class, 'generate']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
});
Route::middleware(['auth:sanctum', 'throttle:account-api'])->group(function () {
    Route::post('/sites/connect', [SiteController::class, 'connect']);
    Route::get('/sites/status', [SiteController::class, 'status']);
    Route::post('/sites/{site}/rotate-token', [SiteController::class, 'rotate']);
    Route::get('/wallet/balance', [WalletController::class, 'balance']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::get('/usage/history', [UsageController::class, 'history']);
});
Route::middleware(['site.token', 'throttle:site-api'])->group(function () {
    Route::post('/ai/detect-news-type', [AiController::class, 'detect']);
    Route::post('/ai/analyze-source', [AiController::class, 'analyzeSource']);
    Route::post('/ai/generate-news', [AiController::class, 'generateNews']);
    Route::post('/ai/generate-article', [AiController::class, 'generateArticle']);
    Route::post('/ai/rewrite', [AiController::class, 'rewrite']);
    Route::post('/ai/generate-image', [AiController::class, 'image']);
});
