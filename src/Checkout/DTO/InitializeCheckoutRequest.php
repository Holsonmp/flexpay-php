<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\DTO;

use Holduix\FlexPay\Enum\PaymentMethod;

final class InitializeCheckoutRequest
{
    public function __construct(
        public readonly string $checkoutId,
        public readonly PaymentMethod $paymentMethod,
        public readonly string $phoneNumber = ''
    ) {
    }
}
