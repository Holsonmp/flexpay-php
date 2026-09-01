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

use Holduix\FlexPay\Enum\PaymentMethod;

final class CreateCheckoutRequest
{
    /**
     * @param list<PaymentMethod> $allowedMethods
     */
    public function __construct(
        public readonly string $merchantReference,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $allowedMethods,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
        public readonly string $callbackUrl,
        public readonly string $customerName = '',
        public readonly string $customerEmail = '',
        public readonly string $description = '',
        public readonly ?\DateTimeImmutable $expiresAt = null
    ) {
    }
}
