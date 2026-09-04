<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\ClientController;
use Illuminate\Support\Facades\Route;

Route::prefix('clients')->group(function () {
    Route::get('/', [ClientController::class, 'index']);
    Route::post('/', [ClientController::class, 'store']);
    Route::get('/{client}', [ClientController::class, 'show']);
    Route::get('/{client}/transactions', [ClientController::class, 'transactions']);

    Route::post('/{client}/deposit', [AccountController::class, 'deposit']);
    Route::post('/{client}/withdraw', [AccountController::class, 'withdraw']);
    Route::post('/{client}/buy', [AccountController::class, 'buy']);
    Route::post('/{client}/sell', [AccountController::class, 'sell']);
});
