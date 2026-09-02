<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes([
    'middleware' => ['auth:sanctum'],
]);

require base_path('routes/channels.php');

Route::get('/', function () {
    return view('welcome');
});