<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Exception;

use Holduix\FlexPay\Checkout\CheckoutState;

final class InvalidCheckoutTransitionException extends CheckoutException
{
    public function __construct(CheckoutState $from, CheckoutState $to)
    {
        parent::__construct(
            sprintf('Transition checkout interdite : %s -> %s.', $from->value, $to->value),
            'invalid_checkout_transition',
            'Paiement : cette transition n’est pas autorisée.'
        );
    }
}
