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

final class CheckoutRedirectException extends CheckoutException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            'URL de redirection FlexPay refusée : ' . $reason,
            'invalid_checkout_redirect',
            'Redirection : l’adresse de paiement retournée par FlexPay n’est pas autorisée.'
        );
    }
}
