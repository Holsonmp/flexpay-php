<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\DTO\Response;

use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;
use JsonSerializable;

abstract class PaymentResult implements JsonSerializable
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly NormalizedPaymentStatus $normalizedStatus,
        public readonly ?string $rawStatus,
        public readonly array $rawResponse,
        public readonly string $transactionReference,
        public readonly ?string $providerTransactionId,
        public readonly PaymentMethod $paymentMethod,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $message = null,
        public readonly ?string $occurredAt = null
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'normalizedStatus' => $this->normalizedStatus->value,
            'rawStatus' => $this->rawStatus,
            'rawResponse' => $this->rawResponse,
            'transactionReference' => $this->transactionReference,
            'providerTransactionId' => $this->providerTransactionId,
            'paymentMethod' => $this->paymentMethod->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'occurredAt' => $this->occurredAt,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
