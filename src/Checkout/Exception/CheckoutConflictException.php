<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Exception;

final class CheckoutConflictException extends CheckoutException
{
    public function __construct(string $message, string $errorCode = 'checkout_conflict')
    {
        parent::__construct(
            $message,
            $errorCode,
            'Paiement : cette transaction a déjà été initialisée ou son état a changé.'
        );
    }
}
