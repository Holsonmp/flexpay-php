<?php

declare(strict_types=1);

namespace Holduix\FlexPay\DTO\Response;

use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use JsonSerializable;

final class WebhookEvent implements JsonSerializable
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly ?string $merchantReference,
        public readonly ?string $providerTransactionId,
        public readonly NormalizedPaymentStatus $normalizedStatus,
        public readonly ?string $rawStatus,
        public readonly ?float $amount,
        public readonly ?string $currency,
        public readonly ?string $message,
        public readonly ?string $occurredAt,
        public readonly array $rawResponse
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'merchantReference' => $this->merchantReference,
            'providerTransactionId' => $this->providerTransactionId,
            'normalizedStatus' => $this->normalizedStatus->value,
            'rawStatus' => $this->rawStatus,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'occurredAt' => $this->occurredAt,
            'rawResponse' => $this->rawResponse,
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
