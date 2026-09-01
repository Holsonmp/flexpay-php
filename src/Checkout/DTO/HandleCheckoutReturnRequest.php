<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\DTO;

final class HandleCheckoutReturnRequest
{
    /** @param array<string, scalar> $queryParameters */
    public function __construct(
        public readonly string $checkoutId,
        public readonly array $queryParameters = []
    ) {
    }
}
