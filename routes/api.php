<?php

use App\Http\Controllers\TallyController;
use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, API!']);
});

Route::post('/postAllData', [TallyController::class, 'postAllData'])
    ->middleware(ApiKeyMiddleware::class);
