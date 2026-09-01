<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

use App\Http\Controllers\FlexPayCheckoutController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function (): void {
    Route::post('/orders/{order}/flexpay-checkout', [FlexPayCheckoutController::class, 'create']);
    Route::middleware(['checkout.owner'])->group(function (): void {
        Route::get('/payments/flexpay/checkouts/{checkoutId}', [FlexPayCheckoutController::class, 'details']);
        Route::post(
            '/payments/flexpay/checkouts/{checkoutId}/initialize',
            [FlexPayCheckoutController::class, 'initialize']
        );
        Route::get('/payments/flexpay/checkouts/{checkoutId}/status', [FlexPayCheckoutController::class, 'status'])
            ->middleware('throttle:flexpay-status');
        Route::get(
            '/payments/flexpay/checkouts/{checkoutId}/return',
            [FlexPayCheckoutController::class, 'paymentReturn']
        );
    });
});
