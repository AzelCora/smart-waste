<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index']);
Route::get('/api/bin-states', [DashboardController::class, 'binStates']);
Route::get('/api/route', [DashboardController::class, 'route']);
