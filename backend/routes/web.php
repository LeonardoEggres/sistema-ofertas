<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MercadoLivreAuthController;

Route::get('/ml/authorize', [MercadoLivreAuthController::class, 'authorize'])->name('ml.authorize');
Route::get('/callback/mercadolivre', [MercadoLivreAuthController::class, 'callback'])->name('ml.callback');
