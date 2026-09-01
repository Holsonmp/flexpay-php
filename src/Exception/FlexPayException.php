<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Exception;

use RuntimeException;
use Throwable;

class FlexPayException extends RuntimeException
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerCode = null,
        public readonly ?string $correlationId = null,
        public readonly ?string $merchantReference = null,
        public readonly ?string $providerTransactionId = null,
        public readonly array $rawResponse = [],
        public readonly bool $retryable = false,
        public readonly bool $ambiguous = false,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
