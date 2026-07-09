<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DownloadController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/convert', [DownloadController::class, 'convert']);
Route::get('/downloads/{id}', [DownloadController::class, 'status']);