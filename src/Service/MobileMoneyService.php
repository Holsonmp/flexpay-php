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
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\DTO\Response\MobileMoneyPaymentResult;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Http\ApiTransport;
use Holduix\FlexPay\Mapper\StatusMapper;
use Holduix\FlexPay\Support\DataExtractor;
use Holduix\FlexPay\Support\Redactor;
use Holduix\FlexPay\Validation\RequestValidator;

final class MobileMoneyService
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

    public function createPayment(MobileMoneyPaymentRequest $request): MobileMoneyPaymentResult
    {
        $phone = $this->validator->validateMobileMoney($request);
        $currency = strtoupper(trim($request->currency));
        $payload = [
            'merchant' => $this->configuration->merchantId,
            'type' => 1,
            'reference' => trim($request->merchantReference),
            'phone' => $phone,
            'amount' => $request->amount,
            'currency' => $currency,
            'callbackUrl' => $request->callbackUrl,
        ];

        $httpResult = $this->transport->requestJson(
            'POST',
            $this->configuration->apiBaseUrl,
            $this->configuration->mobileMoneyEndpoint,
            $payload,
            $request->merchantReference,
            false
        );
        $raw = $httpResult->body + ['_source' => 'package_http_c2b'];
        $mapping = $this->statusMapper->map($raw);
        $providerReference = $this->extractor->firstScalar($raw, [
            'orderNumber',
            'order_number',
            'ordernumber',
            'provider_reference',
            'providerReference',
            'Provider_reference',
            'transaction_id',
        ]);
        $merchantReference = $this->extractor->firstScalar($raw, [
            'merchant_reference',
            'merchantReference',
            'MerchantReference',
            'reference',
            'Reference',
        ]) ?? '';
        $rawAmount = $this->extractor->firstScalar($raw, ['amount', 'amountCustomer']);
        $amount = is_numeric($rawAmount) ? (float) $rawAmount : 0.0;
        $responseCurrency = strtoupper($this->extractor->firstScalar($raw, ['currency']) ?? '');
        $completeSuccessfulConfirmation = $providerReference !== null
            && $merchantReference !== ''
            && hash_equals(trim($request->merchantReference), $merchantReference)
            && $amount > 0
            && abs($amount - $request->amount) <= 0.00001
            && $responseCurrency !== ''
            && hash_equals($currency, $responseCurrency);
        $normalizedStatus = $mapping->normalizedStatus === NormalizedPaymentStatus::SUCCESSFUL
            && !$completeSuccessfulConfirmation
                ? NormalizedPaymentStatus::UNKNOWN
                : $mapping->normalizedStatus;
        $acceptedStatus = $normalizedStatus->isPending()
            || ($normalizedStatus === NormalizedPaymentStatus::SUCCESSFUL && $completeSuccessfulConfirmation);

        return new MobileMoneyPaymentResult(
            accepted: $acceptedStatus && $providerReference !== null,
            normalizedStatus: $normalizedStatus,
            rawStatus: $mapping->rawStatus,
            rawResponse: $this->redactor->redact($raw),
            transactionReference: $merchantReference,
            providerTransactionId: $providerReference,
            amount: $amount,
            currency: $responseCurrency,
            message: $this->extractor->firstScalar($raw, ['message', 'messsage']),
            occurredAt: $this->extractor->firstScalar($raw, ['occurredAt', 'createdAt', 'created_at'])
        );
    }
}
