<?php

use App\Http\Controllers\TallyController;
use Illuminate\Support\Facades\Route;



Route::get('/getAllData', [TallyController::class, 'getAllData']);
