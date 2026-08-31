<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Exception;

use Holduix\FlexPay\Exception\FlexPayException;

class CheckoutException extends FlexPayException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly string $publicMessage,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            message: $message,
            providerCode: $errorCode,
            previous: $previous
        );
    }
}
