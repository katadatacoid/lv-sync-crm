<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RevenueController;
use App\Http\Controllers\Api\syncController;

Route::get('/sync-data', [syncController::class, 'syncFromProduction']);
