<?php

use App\Http\Controllers\TallyController;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, API!']);
});

