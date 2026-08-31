<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\FlexPayCheckoutTransitioned;
use Holduix\FlexPay\Checkout\Contracts\CheckoutEventListenerInterface;
use Holduix\FlexPay\Checkout\Event\CheckoutEvent;

final class DispatchCheckoutEvents implements CheckoutEventListenerInterface
{
    public function handle(CheckoutEvent $event): void
    {
        // Les listeners métier utilisent leur propre clé unique avant commande, billet ou email.
        event(new FlexPayCheckoutTransitioned($event));
    }
}
