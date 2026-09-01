<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay;

use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\Contracts\HostedCardCheckoutInterface;
use Holduix\FlexPay\Http\ApiTransport;
use Holduix\FlexPay\Mapper\StatusMapper;
use Holduix\FlexPay\Service\CardPaymentService;
use Holduix\FlexPay\Service\MobileMoneyService;
use Holduix\FlexPay\Service\TransactionService;
use Holduix\FlexPay\Service\WebhookService;
use Holduix\FlexPay\Support\DataExtractor;
use Holduix\FlexPay\Support\PhoneNumberNormalizer;
use Holduix\FlexPay\Support\Redactor;
use Holduix\FlexPay\Validation\RequestValidator;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class FlexPayClient
{
    private readonly MobileMoneyService $mobileMoneyService;
    private readonly CardPaymentService $cardPaymentService;
    private readonly TransactionService $transactionService;
    private readonly WebhookService $webhookService;

    public function __construct(
        FlexPayConfiguration $configuration,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        ?LoggerInterface $logger = null,
        ?HostedCardCheckoutInterface $hostedCardCheckout = null
    ) {
        $logger ??= new NullLogger();
        $redactor = new Redactor();
        $extractor = new DataExtractor();
        $statusMapper = new StatusMapper($extractor);
        $validator = new RequestValidator(
            new PhoneNumberNormalizer(),
            $configuration->allowInsecureUrls
        );
        $transport = new ApiTransport(
            $configuration,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $logger,
            $redactor,
            $extractor
        );

        $this->mobileMoneyService = new MobileMoneyService(
            $configuration,
            $transport,
            $validator,
            $statusMapper,
            $extractor,
            $redactor
        );
        $this->cardPaymentService = new CardPaymentService(
            $configuration,
            $validator,
            $redactor,
            $hostedCardCheckout
        );
        $this->transactionService = new TransactionService(
            $configuration,
            $transport,
            $validator,
            $statusMapper,
            $extractor,
            $redactor
        );
        $this->webhookService = new WebhookService($configuration, $statusMapper, $extractor, $redactor);
    }

    public function mobileMoney(): MobileMoneyService
    {
        return $this->mobileMoneyService;
    }

    public function card(): CardPaymentService
    {
        return $this->cardPaymentService;
    }

    public function transactions(): TransactionService
    {
        return $this->transactionService;
    }

    public function webhooks(): WebhookService
    {
        return $this->webhookService;
    }
}
