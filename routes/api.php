<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\RevenueController;
use App\Http\Controllers\Api\syncController;

Route::get('/revenue', [RevenueController::class, 'allSBU']);
Route::get('/revenue-sbu', [RevenueController::class, 'revenueSBU']);
Route::get('/sync-data', [syncController::class, 'syncFromProduction']);

Route::get('/get-revenue', [RevenueController::class, 'getRevenueSbu']);
Route::get('/get-revenue-project', [RevenueController::class, 'getRevenueProject']);
Route::get('/get-invoice', [RevenueController::class, 'getInvoiceData']);
Route::get('/get-revenue-by-status', [RevenueController::class, 'getRevenueByStatus']);