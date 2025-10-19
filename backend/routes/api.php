<?php

use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MercadoLivreAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('ofertas')->group(function () {
    Route::get('/testar', [MarketplaceController::class, 'testar']);
    Route::get('/', [MarketplaceController::class, 'index']);
});

Route::get('/ml/token', [MercadoLivreAuthController::class, 'getToken']);
