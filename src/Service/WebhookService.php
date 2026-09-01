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
use Holduix\FlexPay\DTO\Response\WebhookEvent;
use Holduix\FlexPay\Exception\InvalidWebhookException;
use Holduix\FlexPay\Mapper\StatusMapper;
use Holduix\FlexPay\Support\DataExtractor;
use Holduix\FlexPay\Support\Redactor;
use JsonException;

final class WebhookService
{
    public function __construct(
        private readonly FlexPayConfiguration $configuration,
        private readonly StatusMapper $statusMapper,
        private readonly DataExtractor $extractor,
        private readonly Redactor $redactor
    ) {
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @param array<string, scalar> $query
     */
    public function parse(array $headers, string $body, array $query = []): WebhookEvent
    {
        $this->verifyCallbackToken($headers, $query);
        if (strlen($body) > $this->configuration->maxCallbackBodyBytes) {
            throw new InvalidWebhookException('Callback FlexPay : le corps dépasse la taille autorisée.');
        }
        $payload = $this->decodeBody($body);
        $payload = array_merge($query, $payload);
        $this->assertPayloadBudget($payload);

        $merchantReference = $this->extractor->firstScalar($payload, [
            'merchant_reference',
            'merchantReference',
            'MerchantReference',
            'reference',
            'Reference',
        ]);
        $providerReference = $this->extractor->firstScalar($payload, [
            'orderNumber',
            'order_number',
            'ordernumber',
            'provider_reference',
            'providerReference',
            'Provider_reference',
            'transaction_id',
        ]);

        if ($merchantReference === null && $providerReference === null) {
            throw new InvalidWebhookException(
                'Callback FlexPay : une référence marchande ou un identifiant fournisseur est obligatoire.'
            );
        }

        $mapping = $this->statusMapper->map($payload);
        $amount = $this->extractor->firstScalar($payload, ['amount', 'amountCustomer']);

        return new WebhookEvent(
            merchantReference: $merchantReference,
            providerTransactionId: $providerReference,
            normalizedStatus: $mapping->normalizedStatus,
            rawStatus: $mapping->rawStatus,
            amount: is_numeric($amount) ? (float) $amount : null,
            currency: $this->extractor->firstScalar($payload, ['currency']),
            message: $this->extractor->firstScalar($payload, ['message', 'messsage']),
            occurredAt: $this->extractor->firstScalar($payload, ['occurredAt', 'createdAt', 'created_at']),
            rawResponse: $this->redactor->redact($payload)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }

        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        } catch (JsonException $exception) {
            // Le callback legacy accepte également application/x-www-form-urlencoded.
            if (str_starts_with($body, '{') || str_starts_with($body, '[')) {
                throw new InvalidWebhookException('Callback FlexPay : le JSON est invalide.', previous: $exception);
            }
        }

        parse_str($body, $parsedPayload);
        if ($parsedPayload === []) {
            throw new InvalidWebhookException('Callback FlexPay : le corps est illisible.');
        }

        $formPayload = [];
        foreach ($parsedPayload as $key => $value) {
            $formPayload[(string) $key] = $value;
        }

        return $formPayload;
    }

    /**
     * @param array<string, string|list<string>> $headers
     * @param array<string, mixed> $query
     */
    private function verifyCallbackToken(array $headers, array $query): void
    {
        $expectedToken = trim($this->configuration->callbackToken);
        if ($expectedToken === '') {
            if ($this->configuration->allowUnauthenticatedCallbacks) {
                return;
            }
            throw new InvalidWebhookException(
                'Callback FlexPay : authentification obligatoire mais aucun token n’est configuré.'
            );
        }

        $providedToken = '';
        foreach ($headers as $name => $value) {
            if (strtolower($name) !== 'x-flexpay-callback-token') {
                continue;
            }
            if (is_array($value) && count($value) !== 1) {
                throw new InvalidWebhookException('Callback FlexPay : header de token dupliqué.');
            }
            $providedToken = is_array($value) ? trim((string) $value[0]) : trim($value);
            break;
        }
        if (array_key_exists('token', $query)) {
            if (!$this->configuration->allowCallbackTokenInQuery) {
                throw new InvalidWebhookException('Callback FlexPay : le token dans l’URL est refusé.');
            }
            if ($providedToken === '') {
                $candidate = $query['token'];
                $providedToken = is_scalar($candidate) ? trim((string) $candidate) : '';
            }
        }

        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            throw new InvalidWebhookException('Callback FlexPay : token invalide.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function assertPayloadBudget(array $payload): void
    {
        $fields = 0;
        $walk = function (array $values, int $depth) use (&$walk, &$fields): void {
            if ($depth > 16) {
                throw new InvalidWebhookException('Callback FlexPay : structure trop imbriquée.');
            }
            foreach ($values as $key => $value) {
                ++$fields;
                if ($fields > 128 || strlen((string) $key) > 128) {
                    throw new InvalidWebhookException('Callback FlexPay : trop de champs.');
                }
                if (is_array($value)) {
                    $walk($value, $depth + 1);
                    continue;
                }
                if (is_scalar($value) && strlen((string) $value) > 4096) {
                    throw new InvalidWebhookException('Callback FlexPay : valeur trop longue.');
                }
            }
        };
        $walk($payload, 0);
    }
}
