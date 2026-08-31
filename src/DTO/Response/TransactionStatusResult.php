<?php

declare(strict_types=1);

namespace Holduix\FlexPay\DTO\Response;

use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;

final class TransactionStatusResult extends PaymentResult
{
    /**
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        NormalizedPaymentStatus $normalizedStatus,
        ?string $rawStatus,
        array $rawResponse,
        string $transactionReference,
        ?string $providerTransactionId,
        PaymentMethod $paymentMethod,
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
            $paymentMethod,
            $amount,
            $currency,
            $message,
            $occurredAt
        );
    }
}
