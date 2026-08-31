<?php

declare(strict_types=1);

use App\Http\Controllers\FlexPayCallbackController;
use Illuminate\Support\Facades\Route;

Route::post('/payments/flexpay/callback', FlexPayCallbackController::class)->name('flexpay.callback');
// Cette route API n’utilise pas le middleware CSRF web ; token callback + vérification serveur restent obligatoires.
