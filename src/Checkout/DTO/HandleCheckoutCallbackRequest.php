<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\DTO;

final class HandleCheckoutCallbackRequest
{
    /**
     * @param array<string, string|list<string>> $headers
     * @param array<string, scalar> $queryParameters
     */
    public function __construct(
        public readonly array $headers,
        public readonly string $rawBody,
        public readonly array $queryParameters = []
    ) {
    }
}
