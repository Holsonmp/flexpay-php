<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Exception;

final class CheckoutNotFoundException extends CheckoutException
{
    public function __construct(string $checkoutId)
    {
        parent::__construct(
            'Session de paiement introuvable : ' . $checkoutId,
            'checkout_not_found',
            'Session de paiement : cette session n’existe pas ou n’est plus accessible.'
        );
    }
}
