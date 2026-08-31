<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\DTO;

use Holduix\FlexPay\Checkout\CheckoutAction;
use Holduix\FlexPay\Checkout\CheckoutPublicView;

final class CheckoutOperationResult
{
    public function __construct(
        public readonly CheckoutPublicView $checkout,
        public readonly CheckoutAction $action,
        public readonly bool $idempotent = false
    ) {
    }
}
