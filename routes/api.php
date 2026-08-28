<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;

Route::post('/register', [AuthController::class, 'register']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

Route::post('/conversations', [ConversationController::class, 'store'])
    ->middleware('auth:sanctum');

Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
    ->middleware('auth:sanctum');