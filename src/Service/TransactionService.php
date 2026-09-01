<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Service;

use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\DTO\Response\TransactionStatusResult;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\Http\ApiTransport;
use Holduix\FlexPay\Mapper\StatusMapper;
use Holduix\FlexPay\Support\DataExtractor;
use Holduix\FlexPay\Support\Redactor;
use Holduix\FlexPay\Validation\RequestValidator;

final class TransactionService
{
    public function __construct(
        private readonly FlexPayConfiguration $configuration,
        private readonly ApiTransport $transport,
        private readonly RequestValidator $validator,
        private readonly StatusMapper $statusMapper,
        private readonly DataExtractor $extractor,
        private readonly Redactor $redactor
    ) {
    }

    public function getStatus(
        string $providerTransactionId,
        PaymentMethod $paymentMethod = PaymentMethod::MOBILE_MONEY
    ): TransactionStatusResult {
        return $this->requestStatus(
            $this->configuration->apiBaseUrl,
            $this->configuration->transactionStatusEndpoint,
            $providerTransactionId,
            $paymentMethod,
            'package_http_status'
        );
    }

    public function getCardOrderStatus(string $providerTransactionId): TransactionStatusResult
    {
        return $this->requestStatus(
            $this->configuration->cardBaseUrl,
            $this->configuration->cardOrderStatusEndpoint,
            $providerTransactionId,
            PaymentMethod::CARD,
            'package_http_card_status'
        );
    }

    private function requestStatus(
        string $baseUrl,
        string $endpoint,
        string $providerTransactionId,
        PaymentMethod $paymentMethod,
        string $source
    ): TransactionStatusResult {
        $providerTransactionId = $this->validator->validateProviderTransactionId($providerTransactionId);
        $result = $this->transport->requestJson(
            'GET',
            $baseUrl,
            rtrim($endpoint, '/') . '/' . rawurlencode($providerTransactionId),
            null,
            null,
            true
        );
        $raw = $result->body + ['_source' => $source];
        $mapping = $this->statusMapper->map($raw);
        $merchantReference = $this->extractor->firstScalar($raw, [
            'merchant_reference',
            'merchantReference',
            'MerchantReference',
            'reference',
            'Reference',
        ]) ?? '';
        $amount = $this->extractor->firstScalar($raw, ['amount', 'amountCustomer']);
        $numericAmount = is_numeric($amount) ? (float) $amount : 0.0;
        $currency = strtoupper($this->extractor->firstScalar($raw, ['currency']) ?? '');
        $returnedProviderTransactionId = $this->extractor->firstScalar($raw, [
            'orderNumber',
            'order_number',
            'ordernumber',
            'provider_reference',
            'providerReference',
            'transaction_id',
        ]);
        $completeSuccessfulConfirmation = $returnedProviderTransactionId !== null
            && hash_equals($providerTransactionId, $returnedProviderTransactionId)
            && $merchantReference !== ''
            && $numericAmount > 0
            && $currency !== '';
        $normalizedStatus = $mapping->normalizedStatus === NormalizedPaymentStatus::SUCCESSFUL
            && !$completeSuccessfulConfirmation
                ? NormalizedPaymentStatus::UNKNOWN
                : $mapping->normalizedStatus;

        return new TransactionStatusResult(
            normalizedStatus: $normalizedStatus,
            rawStatus: $mapping->rawStatus,
            rawResponse: $this->redactor->redact($raw),
            transactionReference: $merchantReference,
            providerTransactionId: $returnedProviderTransactionId,
            paymentMethod: $paymentMethod,
            amount: $numericAmount,
            currency: $currency,
            message: $this->extractor->firstScalar($raw, ['message', 'messsage']),
            occurredAt: $this->extractor->firstScalar($raw, ['occurredAt', 'createdAt', 'created_at'])
        );
    }
}
