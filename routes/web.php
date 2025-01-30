<?php

use App\Http\Controllers\TallyController;
use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Support\Facades\Route;



Route::get('/getAllData', [TallyController::class, 'getAllData'])
    ->middleware(ApiKeyMiddleware::class);
Route::post('/postAllData', [TallyController::class, 'postAllData'])
    ->middleware(ApiKeyMiddleware::class);
