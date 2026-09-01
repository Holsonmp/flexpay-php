<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Holduix\FlexPay\Checkout\Contracts\CheckoutStoreInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CheckoutOwner
{
    public function __construct(private readonly CheckoutStoreInterface $store)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $session = $this->store->findById((string) $request->route('checkoutId'));
        abort_if($session === null, 404);
        $order = Order::query()->where('reference', $session->merchantReference())->first();
        abort_if($order === null, 404);
        abort_unless($request->user()?->can('pay', $order) === true, 403);
        return $next($request);
    }
}
