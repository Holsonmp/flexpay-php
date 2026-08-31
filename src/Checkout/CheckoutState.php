<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout;

enum CheckoutState: string
{
    case CREATED = 'created';
    case CHOOSING_METHOD = 'choosing_method';
    case COLLECTING_DETAILS = 'collecting_details';
    case INITIALIZING = 'initializing';
    case PENDING = 'pending';
    case REQUIRES_ACTION = 'requires_action';
    case REDIRECTING = 'redirecting';
    case VERIFYING = 'verifying';
    case SUCCESSFUL = 'successful';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case UNKNOWN = 'unknown';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCESSFUL, self::FAILED, self::CANCELLED, self::EXPIRED], true);
    }
}
