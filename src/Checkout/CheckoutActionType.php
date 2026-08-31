<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout;

enum CheckoutActionType: string
{
    case NONE = 'none';
    case POLL = 'poll';
    case REDIRECT = 'redirect';
}
