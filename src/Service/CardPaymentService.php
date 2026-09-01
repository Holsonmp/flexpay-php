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
use Holduix\FlexPay\Contracts\HostedCardCheckoutInterface;
use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Response\CardPaymentResult;
use Holduix\FlexPay\Exception\UnsupportedOperationException;
use Holduix\FlexPay\Exception\ValidationException;
use Holduix\FlexPay\Support\Redactor;
use Holduix\FlexPay\Validation\RequestValidator;

final class CardPaymentService
{
    public function __construct(
        private readonly FlexPayConfiguration $configuration,
        private readonly RequestValidator $validator,
        private readonly Redactor $redactor,
        private readonly ?HostedCardCheckoutInterface $hostedCheckout = null
    ) {
    }

    public function createPayment(CardPaymentRequest $request): CardPaymentResult
    {
        $phone = $this->validator->validateCard($request);
        if ($this->hostedCheckout === null) {
            throw new UnsupportedOperationException(
                'Checkout carte désactivé : aucun adaptateur fournisseur confirmé et sûr pour le navigateur.'
            );
        }

        $result = $this->hostedCheckout->createCheckout($request, $phone);
        $this->assertSafeResult($request, $result);

        return new CardPaymentResult(
            accepted: $result->accepted,
            checkoutUrl: $result->checkoutUrl,
            normalizedStatus: $result->normalizedStatus,
            rawStatus: $result->rawStatus,
            rawResponse: $this->redactor->redact($result->rawResponse),
            transactionReference: $result->transactionReference,
            providerTransactionId: $result->providerTransactionId,
            paymentMethod: $result->paymentMethod,
            amount: $result->amount,
            currency: $result->currency,
            message: $result->message,
            occurredAt: $result->occurredAt
        );
    }

    private function assertSafeResult(CardPaymentRequest $request, CardPaymentResult $result): void
    {
        $url = filter_var($result->checkoutUrl, FILTER_VALIDATE_URL);
        $scheme = strtolower((string) parse_url($result->checkoutUrl, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($result->checkoutUrl, PHP_URL_HOST));
        $expectedHost = strtolower((string) parse_url($this->configuration->cardBaseUrl, PHP_URL_HOST));
        if (
            $url === false
            || ($scheme !== 'https' && !($scheme === 'http' && $this->configuration->allowInsecureUrls))
            || $host === ''
            || !hash_equals($expectedHost, $host)
            || parse_url($result->checkoutUrl, PHP_URL_USER) !== null
            || parse_url($result->checkoutUrl, PHP_URL_PASS) !== null
        ) {
            throw new ValidationException('Checkout carte : l’URL retournée par l’adaptateur est refusée.');
        }

        $apiToken = trim($this->configuration->apiToken);
        $apiToken = stripos($apiToken, 'bearer ') === 0 ? trim(substr($apiToken, 7)) : $apiToken;
        $decodedUrl = $this->fullyDecodeUrl($result->checkoutUrl);
        foreach ([$apiToken, trim($this->configuration->callbackToken)] as $serverSecret) {
            if ($serverSecret !== '' && str_contains($decodedUrl, $serverSecret)) {
                throw new ValidationException(
                    'Checkout carte : un secret serveur ne peut pas être exposé au navigateur.'
                );
            }
        }

        if (
            $result->transactionReference !== trim($request->merchantReference)
            || abs($result->amount - $request->amount) > 0.00001
            || strtoupper($result->currency) !== strtoupper(trim($request->currency))
            || $result->paymentMethod !== $request->paymentMethod
        ) {
            throw new ValidationException(
                'Checkout carte : le résultat de l’adaptateur ne correspond pas à la requête.'
            );
        }
    }

    private function fullyDecodeUrl(string $url): string
    {
        if (strlen($url) > 4096) {
            throw new ValidationException('Checkout carte : l’URL retournée par l’adaptateur est trop longue.');
        }

        $decoded = $url;
        for ($iteration = 0, $limit = strlen($url) + 1; $iteration < $limit; ++$iteration) {
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                return $decoded;
            }
            $decoded = $next;
        }

        throw new ValidationException('Checkout carte : l’encodage de l’URL est invalide.');
    }
}
