<?php

declare(strict_types=1);

namespace Holduix\FlexPay\DTO\Request;

use Holduix\FlexPay\Enum\PaymentMethod;

final class CardPaymentRequest
{
    public function __construct(
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $merchantReference,
        public readonly string $description,
        public readonly string $callbackUrl,
        public readonly string $approveUrl,
        public readonly string $cancelUrl,
        public readonly string $declineUrl,
        public readonly string $homeUrl,
        public readonly string $customerPhone = '',
        public readonly PaymentMethod $paymentMethod = PaymentMethod::CARD
    ) {
    }
}
