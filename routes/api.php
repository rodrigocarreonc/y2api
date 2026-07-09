<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DownloadController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('throttle:conversions')->group(function () {
    Route::post('/convert', [DownloadController::class, 'convert']);
});
Route::get('/downloads/{id}', [DownloadController::class, 'status']);
Route::get('/downloads/{id}/file', [DownloadController::class, 'forceDownload']);