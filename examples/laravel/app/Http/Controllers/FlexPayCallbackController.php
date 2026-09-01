<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use Holduix\FlexPay\Checkout\CheckoutManager;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutCallbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FlexPayCallbackController extends Controller
{
    public function __invoke(Request $request, CheckoutManager $checkout): JsonResponse
    {
        $checkout->handleCallback(new HandleCheckoutCallbackRequest(
            $request->headers->all(),
            $request->getContent(),
            $request->query()
        ));
        return response()->json(['acknowledged' => true]);
    }
}
