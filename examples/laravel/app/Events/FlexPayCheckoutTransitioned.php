<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace App\Events;

use Holduix\FlexPay\Checkout\Event\CheckoutEvent;

final class FlexPayCheckoutTransitioned
{
    public function __construct(public readonly CheckoutEvent $checkoutEvent)
    {
    }
}
