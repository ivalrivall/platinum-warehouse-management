<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductCategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('auth/token', [AuthController::class, 'token']);
Route::post('auth/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function($route){
    Route::resource('product-categories', ProductCategoryController::class);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
