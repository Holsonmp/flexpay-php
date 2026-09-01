<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Http;

use Closure;
use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\Exception\ApiException;
use Holduix\FlexPay\Exception\AuthenticationException;
use Holduix\FlexPay\Exception\AuthorizationException;
use Holduix\FlexPay\Exception\InvalidResponseException;
use Holduix\FlexPay\Exception\NetworkException;
use Holduix\FlexPay\Exception\RateLimitException;
use Holduix\FlexPay\Exception\TimeoutException;
use Holduix\FlexPay\Support\DataExtractor;
use Holduix\FlexPay\Support\Redactor;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface as PsrNetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class ApiTransport
{
    private readonly Closure $sleeper;

    public function __construct(
        private readonly FlexPayConfiguration $configuration,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly LoggerInterface $logger,
        private readonly Redactor $redactor,
        private readonly DataExtractor $extractor,
        ?callable $sleeper = null
    ) {
        $this->sleeper = $sleeper !== null
            ? Closure::fromCallable($sleeper)
            : static function (int $milliseconds): void {
                usleep($milliseconds * 1000);
            };
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function requestJson(
        string $method,
        string $baseUrl,
        string $path,
        ?array $payload,
        ?string $merchantReference,
        bool $safeToRetry
    ): HttpResult {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $correlationId = bin2hex(random_bytes(12));
        $maximumAttempts = $safeToRetry ? $this->configuration->retry->maxAttempts : 1;

        for ($attempt = 1; $attempt <= $maximumAttempts; $attempt++) {
            $startedAt = microtime(true);
            try {
                $request = $this->requestFactory->createRequest(strtoupper($method), $url)
                    ->withHeader('Accept', 'application/json')
                    ->withHeader('Content-Type', 'application/json')
                    ->withHeader('Authorization', $this->authorizationHeader());

                if ($payload !== null) {
                    $json = json_encode(
                        $payload,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                    );
                    $request = $request->withBody($this->streamFactory->createStream($json));
                }

                $response = $this->httpClient->sendRequest($request);
                $duration = (int) round((microtime(true) - $startedAt) * 1000);
                $this->logResponse($method, $baseUrl, $response, $duration, $attempt, $correlationId);

                $mustRetry = $safeToRetry
                    && $attempt < $maximumAttempts
                    && $this->isRetryableStatus($response->getStatusCode());
                if ($mustRetry) {
                    $this->sleepBeforeRetry($attempt, $response);
                    continue;
                }

                return $this->decodeResponse($response, $merchantReference, $correlationId, $safeToRetry);
            } catch (PsrNetworkExceptionInterface $exception) {
                if ($safeToRetry && $attempt < $maximumAttempts) {
                    $this->logRetry($method, $baseUrl, $attempt, $correlationId, $exception);
                    $this->sleepBeforeRetry($attempt);
                    continue;
                }

                $this->throwNetworkException($exception, $merchantReference, $correlationId, !$safeToRetry);
            } catch (ClientExceptionInterface $exception) {
                throw new NetworkException(
                    'Erreur réseau lors de la communication avec FlexPay.',
                    correlationId: $correlationId,
                    merchantReference: $merchantReference,
                    retryable: $safeToRetry,
                    ambiguous: !$safeToRetry,
                    previous: $exception
                );
            } catch (JsonException $exception) {
                throw new InvalidResponseException(
                    'Impossible d’encoder la requête FlexPay.',
                    correlationId: $correlationId,
                    merchantReference: $merchantReference,
                    previous: $exception
                );
            }
        }

        throw new NetworkException(
            'La communication avec FlexPay a échoué après plusieurs tentatives.',
            correlationId: $correlationId,
            merchantReference: $merchantReference,
            retryable: $safeToRetry,
            ambiguous: !$safeToRetry
        );
    }

    private function authorizationHeader(): string
    {
        $token = trim($this->configuration->apiToken);
        return stripos($token, 'bearer ') === 0 ? $token : 'Bearer ' . $token;
    }

    private function decodeResponse(
        ResponseInterface $response,
        ?string $merchantReference,
        string $correlationId,
        bool $safeToRetry
    ): HttpResult {
        $statusCode = $response->getStatusCode();
        $rawBody = trim($this->readBoundedBody($response, $merchantReference, $correlationId));
        $decoded = [];

        if ($rawBody !== '') {
            try {
                $candidate = json_decode($rawBody, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new InvalidResponseException(
                    'Réponse FlexPay invalide : le corps reçu n’est pas un objet JSON.',
                    httpStatus: $statusCode,
                    correlationId: $correlationId,
                    merchantReference: $merchantReference,
                    rawResponse: [
                        'body_length' => strlen($rawBody),
                        'body_sha256' => hash('sha256', $rawBody),
                        'content_type' => $response->getHeaderLine('Content-Type'),
                    ],
                    previous: $exception
                );
            }
            if (!is_array($candidate)) {
                throw new InvalidResponseException(
                    'Réponse FlexPay invalide : un objet JSON était attendu.',
                    httpStatus: $statusCode,
                    correlationId: $correlationId,
                    merchantReference: $merchantReference
                );
            }
            /** @var array<string, mixed> $candidate */
            $this->assertDecodedResponseBudget($candidate, $merchantReference, $correlationId, $statusCode);
            $decoded = $candidate;
        }

        $decoded['_http_code'] = $statusCode;
        $sanitized = $this->redactor->redact($decoded);

        if ($statusCode < 200 || $statusCode >= 300) {
            $this->throwApiException($statusCode, $sanitized, $merchantReference, $correlationId, $safeToRetry);
        }

        if ($rawBody === '') {
            throw new InvalidResponseException(
                'Réponse FlexPay invalide : le corps est vide.',
                httpStatus: $statusCode,
                correlationId: $correlationId,
                merchantReference: $merchantReference
            );
        }

        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[(string) $name] = array_values($values);
        }

        // La réponse exploitable reste intacte pour les identifiants métier.
        // Chaque DTO public applique ensuite la redaction à son seul champ rawResponse.
        return new HttpResult($statusCode, $decoded, $headers, $correlationId);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function throwApiException(
        int $statusCode,
        array $body,
        ?string $merchantReference,
        string $correlationId,
        bool $safeToRetry
    ): never {
        $providerCode = $this->extractor->firstScalar($body, ['code', 'response_code', 'responseCode']);
        $message = sprintf('FlexPay a répondu avec le code HTTP %d.', $statusCode);
        $arguments = [
            'message' => $message,
            'httpStatus' => $statusCode,
            'providerCode' => $providerCode,
            'correlationId' => $correlationId,
            'merchantReference' => $merchantReference,
            'rawResponse' => $body,
            'retryable' => $safeToRetry && $this->isRetryableStatus($statusCode),
            'ambiguous' => false,
        ];

        if ($statusCode === 401) {
            throw new AuthenticationException(...$arguments);
        }
        if ($statusCode === 403) {
            throw new AuthorizationException(...$arguments);
        }
        if ($statusCode === 429) {
            throw new RateLimitException(...$arguments);
        }

        throw new ApiException(...$arguments);
    }

    private function throwNetworkException(
        PsrNetworkExceptionInterface $exception,
        ?string $merchantReference,
        string $correlationId,
        bool $ambiguous
    ): never {
        $isTimeout = str_contains(strtolower($exception->getMessage()), 'timeout')
            || str_contains(strtolower($exception->getMessage()), 'timed out');
        $class = $isTimeout ? TimeoutException::class : NetworkException::class;

        throw new $class(
            $isTimeout ? 'La requête FlexPay a expiré.' : 'Erreur réseau lors de la communication avec FlexPay.',
            correlationId: $correlationId,
            merchantReference: $merchantReference,
            retryable: !$ambiguous,
            ambiguous: $ambiguous,
            previous: $exception
        );
    }

    private function isRetryableStatus(int $statusCode): bool
    {
        return in_array($statusCode, [429, 502, 503, 504], true);
    }

    private function sleepBeforeRetry(int $attempt, ?ResponseInterface $response = null): void
    {
        $retryAfter = $response?->getHeaderLine('Retry-After') ?? '';
        if ($retryAfter !== '' && ctype_digit($retryAfter)) {
            $delay = min((int) $retryAfter * 1000, $this->configuration->retry->maxDelayMilliseconds);
        } else {
            $delay = min(
                $this->configuration->retry->baseDelayMilliseconds * (2 ** ($attempt - 1)),
                $this->configuration->retry->maxDelayMilliseconds
            );
            if ($this->configuration->retry->jitter && $delay > 1) {
                $delay = random_int((int) floor($delay / 2), $delay);
            }
        }

        ($this->sleeper)($delay);
    }

    private function logResponse(
        string $method,
        string $baseUrl,
        ResponseInterface $response,
        int $durationMilliseconds,
        int $attempt,
        string $correlationId
    ): void {
        $this->logger->info('Réponse HTTP FlexPay reçue.', [
            'method' => strtoupper($method),
            'provider_host' => (string) parse_url($baseUrl, PHP_URL_HOST),
            'http_status' => $response->getStatusCode(),
            'duration_ms' => $durationMilliseconds,
            'attempt' => $attempt,
            'correlation_id' => $correlationId,
        ]);
    }

    private function logRetry(
        string $method,
        string $baseUrl,
        int $attempt,
        string $correlationId,
        Throwable $exception
    ): void {
        $this->logger->warning('Nouvelle tentative de lecture FlexPay après erreur réseau.', [
            'method' => strtoupper($method),
            'provider_host' => (string) parse_url($baseUrl, PHP_URL_HOST),
            'attempt' => $attempt,
            'correlation_id' => $correlationId,
            'error_type' => $exception::class,
        ]);
    }

    private function readBoundedBody(
        ResponseInterface $response,
        ?string $merchantReference,
        string $correlationId
    ): string {
        $stream = $response->getBody();
        $maximum = $this->configuration->maxProviderResponseBytes;
        $size = $stream->getSize();
        if ($size !== null && $size > $maximum) {
            throw new InvalidResponseException(
                'Réponse FlexPay refusée : le corps dépasse la taille autorisée.',
                httpStatus: $response->getStatusCode(),
                correlationId: $correlationId,
                merchantReference: $merchantReference,
                rawResponse: ['body_length' => $size]
            );
        }
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = '';
        while (!$stream->eof()) {
            $remaining = $maximum + 1 - strlen($body);
            if ($remaining <= 0) {
                break;
            }
            $chunk = $stream->read(min(8192, $remaining));
            if ($chunk === '') {
                break;
            }
            $body .= $chunk;
        }
        if (strlen($body) > $maximum) {
            throw new InvalidResponseException(
                'Réponse FlexPay refusée : le corps dépasse la taille autorisée.',
                httpStatus: $response->getStatusCode(),
                correlationId: $correlationId,
                merchantReference: $merchantReference,
                rawResponse: ['body_length_greater_than' => $maximum]
            );
        }

        return $body;
    }

    /** @param array<string, mixed> $payload */
    private function assertDecodedResponseBudget(
        array $payload,
        ?string $merchantReference,
        string $correlationId,
        int $statusCode
    ): void {
        $fields = 0;
        $walk = function (
            array $values,
            int $depth
        ) use (
            &$walk,
            &$fields,
            $merchantReference,
            $correlationId,
            $statusCode
        ): void {
            if ($depth > 16) {
                throw new InvalidResponseException(
                    'Réponse FlexPay refusée : structure trop imbriquée.',
                    httpStatus: $statusCode,
                    correlationId: $correlationId,
                    merchantReference: $merchantReference
                );
            }
            foreach ($values as $key => $value) {
                ++$fields;
                if ($fields > 512 || strlen((string) $key) > 256) {
                    throw new InvalidResponseException(
                        'Réponse FlexPay refusée : trop de champs.',
                        httpStatus: $statusCode,
                        correlationId: $correlationId,
                        merchantReference: $merchantReference
                    );
                }
                if (is_array($value)) {
                    $walk($value, $depth + 1);
                    continue;
                }
                if (is_scalar($value) && strlen((string) $value) > 65536) {
                    throw new InvalidResponseException(
                        'Réponse FlexPay refusée : valeur trop longue.',
                        httpStatus: $statusCode,
                        correlationId: $correlationId,
                        merchantReference: $merchantReference
                    );
                }
            }
        };
        $walk($payload, 0);
    }
}
