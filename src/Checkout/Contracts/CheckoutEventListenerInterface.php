<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Contracts;

use Holduix\FlexPay\Checkout\Event\CheckoutEvent;

interface CheckoutEventListenerInterface
{
    public function handle(CheckoutEvent $event): void;
}
