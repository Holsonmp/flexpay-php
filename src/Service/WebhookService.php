<?php

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
        $payload = $this->decodeBody($body);
        $payload = array_merge($query, $payload);
        $this->verifyCallbackToken($headers, $payload, $query);

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
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($decoded)) {
                /** @var array<string, mixed> $decoded */
                return $decoded;
            }
        } catch (JsonException) {
            // Le callback legacy accepte également application/x-www-form-urlencoded.
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
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $query
     */
    private function verifyCallbackToken(array $headers, array $payload, array $query): void
    {
        $expectedToken = trim($this->configuration->callbackToken);
        if ($expectedToken === '') {
            return;
        }

        $tokenCandidate = $query['token'] ?? $payload['token'] ?? '';
        $providedToken = is_scalar($tokenCandidate) ? trim((string) $tokenCandidate) : '';
        if ($providedToken === '') {
            foreach ($headers as $name => $value) {
                if (strtolower($name) !== 'x-flexpay-callback-token') {
                    continue;
                }
                $providedToken = is_array($value) ? trim((string) ($value[0] ?? '')) : trim($value);
                break;
            }
        }

        if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            throw new InvalidWebhookException('Callback FlexPay : token invalide.');
        }
    }
}
