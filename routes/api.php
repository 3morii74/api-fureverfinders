<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\JWTAuthController;
use App\Http\Controllers\Pets;
use App\Http\Controllers\PetsController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\JwtMiddleware;

// Route::post('/register', [JWTAuthController::class, 'register']);
// Route::post('login', [JWTAuthController::class, 'login']);
// Route::get('/pets/index', [Pets::class, 'index']);
// Route::get('/pets/one', [Pets::class, 'show']);
// Route::middleware([JwtMiddleware::class])->group(function () {
//     Route::get('user', [JWTAuthController::class, 'getUser']);
//     Route::post('logout', [JWTAuthController::class, 'logout']);
//     Route::get('/user/show', [UserController::class, 'show']);
//     Route::delete('/user/deletepet', [UserController::class, 'destroy']);
//     Route::post('/pets/store', [Pets::class, 'store']);
// });
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::middleware('auth:api')->get('user', [AuthController::class, 'me']);
Route::middleware('auth:api')->post('logout', [AuthController::class, 'logout']);
Route::middleware('auth:api')->group(function () {
    // Store a new cat's information
    Route::post('/cats', [PetsController::class, 'store']);

    // Retrieve all cats with user information
    Route::get('/cats', [PetsController::class, 'index']);
});
