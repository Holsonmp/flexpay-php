<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Event;

use DateTimeImmutable;
use Holduix\FlexPay\Checkout\CheckoutPublicView;
use Holduix\FlexPay\Checkout\CheckoutTransition;

final class CheckoutEvent
{
    public function __construct(
        public readonly string $name,
        public readonly CheckoutPublicView $checkout,
        public readonly DateTimeImmutable $occurredAt,
        public readonly ?CheckoutTransition $transition = null
    ) {
    }
}
