<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/conversations', [ConversationController::class, 'index']);

    Route::post('/conversations', [ConversationController::class, 'store']);

    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);

    Route::get(
        '/conversations/{conversation}/messages',
        [MessageController::class, 'index']
    );

    Route::post(
        '/conversations/{conversation}/messages',
        [MessageController::class, 'store']
    );

    Route::patch(
        '/conversations/{conversation}/messages/read',
        [MessageController::class, 'markAsRead']
    );
});
