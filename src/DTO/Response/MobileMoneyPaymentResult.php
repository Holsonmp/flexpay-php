<?php

declare(strict_types=1);

namespace Holduix\FlexPay\DTO\Response;

use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;

final class MobileMoneyPaymentResult extends PaymentResult
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        public readonly bool $accepted,
        NormalizedPaymentStatus $normalizedStatus,
        ?string $rawStatus,
        array $rawResponse,
        string $transactionReference,
        ?string $providerTransactionId,
        float $amount,
        string $currency,
        ?string $message = null,
        ?string $occurredAt = null
    ) {
        parent::__construct(
            $normalizedStatus,
            $rawStatus,
            $rawResponse,
            $transactionReference,
            $providerTransactionId,
            PaymentMethod::MOBILE_MONEY,
            $amount,
            $currency,
            $message,
            $occurredAt
        );
    }

    public function toArray(): array
    {
        return ['accepted' => $this->accepted] + parent::toArray();
    }
}
