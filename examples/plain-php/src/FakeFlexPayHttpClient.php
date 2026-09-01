<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Example;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeFlexPayHttpClient implements ClientInterface
{
    public function __construct(private readonly string $counterFile)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return $this->json([
                'status' => 'pending',
                'reference' => $payload['reference'],
                'orderNumber' => 'FAKE-' . $payload['reference'],
                'amount' => $payload['amount'],
                'currency' => $payload['currency'],
            ]);
        }

        $providerReference = basename($request->getUri()->getPath());
        $count = $this->increment($providerReference);
        $merchantReference = str_replace('FAKE-', '', $providerReference);
        return $this->json([
            'status' => $count >= 3 ? 'success' : 'pending',
            'merchant_reference' => $merchantReference,
            'orderNumber' => $providerReference,
            'amount' => 50,
            'currency' => 'USD',
        ]);
    }

    private function json(array $payload): ResponseInterface
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function increment(string $key): int
    {
        $counters = is_file($this->counterFile)
            ? json_decode((string) file_get_contents($this->counterFile), true, 512, JSON_THROW_ON_ERROR)
            : [];
        $counters[$key] = (int) ($counters[$key] ?? 0) + 1;
        file_put_contents($this->counterFile, json_encode($counters, JSON_THROW_ON_ERROR));
        return $counters[$key];
    }
}
