<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

use App\Http\Controllers\FlexPayCallbackController;
use Illuminate\Support\Facades\Route;

Route::post('/payments/flexpay/callback', FlexPayCallbackController::class)
    ->middleware('throttle:flexpay-callback')
    ->name('flexpay.callback');
// Cette route API n’utilise pas le middleware CSRF web ; token callback + vérification serveur restent obligatoires.
