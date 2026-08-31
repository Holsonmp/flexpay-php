<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Http;

final class HttpResult
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, array<int, string>> $headers
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly array $body,
        public readonly array $headers,
        public readonly string $correlationId
    ) {
    }
}
