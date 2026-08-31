<?php

declare(strict_types=1);

namespace Holduix\FlexPay\DTO\Request;

final class MobileMoneyPaymentRequest
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $phoneNumber,
        public readonly string $merchantReference,
        public readonly string $callbackUrl,
        public readonly string $description = ''
    ) {
    }
}
