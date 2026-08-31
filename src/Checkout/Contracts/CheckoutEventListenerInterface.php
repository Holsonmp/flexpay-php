<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Contracts;

use Holduix\FlexPay\Checkout\Event\CheckoutEvent;

interface CheckoutEventListenerInterface
{
    public function handle(CheckoutEvent $event): void;
}
