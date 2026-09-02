<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ReactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/users', [ConversationController::class, 'users']);
        Route::prefix('/conversations')->group(function () {
            Route::get('/', [ConversationController::class, 'index']);
            Route::post('/', [ConversationController::class, 'store']);
            Route::get('/{conversation}', [ConversationController::class, 'show']);

            Route::prefix('/{conversation}')->group(function () {
                Route::get('/messages', [MessageController::class, 'index']);
                Route::post('/messages', [MessageController::class, 'store']);
                Route::patch('/messages/read', [MessageController::class, 'markAsRead']);
                Route::patch('/messages/{message}', [MessageController::class, 'update']);
                Route::delete('/messages/{message}', [MessageController::class, 'destroy']);

                Route::post('/messages/{message}/reactions', [ReactionController::class, 'store']);
                Route::delete('/messages/{message}/reactions', [ReactionController::class, 'destroy']);
            });
        });
    });
});
