<?php

use Illuminate\Support\Facades\Route;
use Mandiri\Qris\Http\Controllers\QrisController;

/*
|--------------------------------------------------------------------------
| Mandiri QRIS API Routes
|--------------------------------------------------------------------------
|
| Add these routes to your routes/api.php file
|
*/

Route::prefix('qris')->group(function () {
    Route::post('/create', [QrisController::class, 'create']);
    Route::get('/status/{qrId}', [QrisController::class, 'checkStatus']);
    Route::post('/webhook', [QrisController::class, 'webhook']);
});
