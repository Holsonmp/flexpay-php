<?php

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
        $acceptedStatus = $mapping->normalizedStatus === NormalizedPaymentStatus::SUCCESSFUL
            || $mapping->normalizedStatus->isPending();

        return new MobileMoneyPaymentResult(
            accepted: $acceptedStatus && $providerReference !== null,
            normalizedStatus: $mapping->normalizedStatus,
            rawStatus: $mapping->rawStatus,
            rawResponse: $this->redactor->redact($raw),
            transactionReference: trim($request->merchantReference),
            providerTransactionId: $providerReference,
            amount: $request->amount,
            currency: $currency,
            message: $this->extractor->firstScalar($raw, ['message', 'messsage']),
            occurredAt: $this->extractor->firstScalar($raw, ['occurredAt', 'createdAt', 'created_at'])
        );
    }
}
