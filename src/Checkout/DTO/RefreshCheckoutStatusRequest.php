<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\DTO;

final class RefreshCheckoutStatusRequest
{
    public function __construct(
        public readonly string $checkoutId,
        public readonly bool $forceProviderRefresh = false
    ) {
    }
}
