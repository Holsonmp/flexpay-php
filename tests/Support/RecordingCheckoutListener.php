<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Support;

use Holduix\FlexPay\Checkout\Contracts\CheckoutEventListenerInterface;
use Holduix\FlexPay\Checkout\Event\CheckoutEvent;

final class RecordingCheckoutListener implements CheckoutEventListenerInterface
{
    /** @var list<CheckoutEvent> */
    public array $events = [];

    public function handle(CheckoutEvent $event): void
    {
        $this->events[] = $event;
    }
}
