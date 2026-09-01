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

use Holduix\FlexPay\Checkout\CheckoutSession;
use Holduix\FlexPay\Checkout\CheckoutTransition;
use Holduix\FlexPay\Checkout\Contracts\CheckoutStoreInterface;
use Holduix\FlexPay\Checkout\Exception\CheckoutConflictException;

final class FileCheckoutStore implements CheckoutStoreInterface
{
    public function __construct(private readonly string $file)
    {
        if (!is_file($this->file)) {
            $directory = dirname($this->file);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            file_put_contents($this->file, json_encode([
                'sessions' => [],
                'transitions' => [],
                'callbacks' => [],
            ], JSON_THROW_ON_ERROR));
        }
    }

    public function findById(string $checkoutId): ?CheckoutSession
    {
        return $this->find(static fn (array $row): bool => $row['checkout_id'] === $checkoutId);
    }

    public function findByMerchantReference(string $merchantReference): ?CheckoutSession
    {
        return $this->find(static fn (array $row): bool => $row['merchant_reference'] === $merchantReference);
    }

    public function findByProviderTransactionId(string $providerTransactionId): ?CheckoutSession
    {
        return $this->find(
            static fn (array $row): bool => ($row['provider_transaction_id'] ?? null) === $providerTransactionId
        );
    }

    public function save(CheckoutSession $session, ?int $expectedVersion = null): void
    {
        $this->mutate(function (array &$data) use ($session, $expectedVersion): void {
            $stored = $data['sessions'][$session->id()] ?? null;
            if ($expectedVersion === null && $stored !== null) {
                throw new CheckoutConflictException('Checkout déjà présent.', 'duplicate_checkout');
            }
            if ($expectedVersion !== null && (int) ($stored['version'] ?? -1) !== $expectedVersion) {
                throw new CheckoutConflictException('Version concurrente.', 'concurrent_update');
            }
            $data['sessions'][$session->id()] = $session->toStorageArray();
        });
    }

    public function recordTransition(CheckoutTransition $transition): void
    {
        $this->mutate(static function (array &$data) use ($transition): void {
            $key = $transition->checkoutId . ':' . $transition->version;
            $data['transitions'][$key] = $transition->toArray();
        });
    }

    public function hasProcessedCallback(string $eventId): bool
    {
        $data = $this->read();
        return isset($data['callbacks'][$eventId]);
    }

    public function markCallbackProcessed(string $eventId, string $checkoutId): void
    {
        $this->mutate(static function (array &$data) use ($eventId, $checkoutId): void {
            $data['callbacks'][$eventId] = $checkoutId;
        });
    }

    /** @param callable(array<string, mixed>): bool $predicate */
    private function find(callable $predicate): ?CheckoutSession
    {
        foreach ($this->read()['sessions'] as $row) {
            if ($predicate($row)) {
                return CheckoutSession::fromStorageArray($row);
            }
        }
        return null;
    }

    /** @return array<string, mixed> */
    private function read(): array
    {
        return json_decode((string) file_get_contents($this->file), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param callable(array<string, mixed>&): void $callback */
    private function mutate(callable $callback): void
    {
        $handle = fopen($this->file, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Verrou du stockage de démonstration indisponible.');
        }
        try {
            rewind($handle);
            $content = stream_get_contents($handle);
            $data = json_decode($content ?: '{}', true, 512, JSON_THROW_ON_ERROR);
            $data += ['sessions' => [], 'transitions' => [], 'callbacks' => []];
            $callback($data);
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
