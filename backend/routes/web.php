<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WebAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/dashboard');
});
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login']);
    Route::get('/register', [WebAuthController::class, 'registerForm']);
    Route::post('/register', [WebAuthController::class, 'register']);
});
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::post('/logout', [WebAuthController::class, 'logout']);
});
