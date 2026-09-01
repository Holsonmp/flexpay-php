<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Support;

use Holduix\FlexPay\Checkout\CheckoutSession;
use Holduix\FlexPay\Checkout\CheckoutTransition;
use Holduix\FlexPay\Checkout\Contracts\CheckoutStoreInterface;
use Holduix\FlexPay\Checkout\Exception\CheckoutConflictException;

final class InMemoryCheckoutStore implements CheckoutStoreInterface
{
    /** @var array<string, CheckoutSession> */
    private array $sessions = [];

    /** @var list<CheckoutTransition> */
    private array $transitions = [];

    /** @var array<string, string> */
    private array $callbacks = [];

    public function findById(string $checkoutId): ?CheckoutSession
    {
        return isset($this->sessions[$checkoutId]) ? clone $this->sessions[$checkoutId] : null;
    }

    public function findByMerchantReference(string $merchantReference): ?CheckoutSession
    {
        foreach ($this->sessions as $session) {
            if ($session->merchantReference() === $merchantReference) {
                return clone $session;
            }
        }

        return null;
    }

    public function findByProviderTransactionId(string $providerTransactionId): ?CheckoutSession
    {
        foreach ($this->sessions as $session) {
            if ($session->providerTransactionId() === $providerTransactionId) {
                return clone $session;
            }
        }

        return null;
    }

    public function save(CheckoutSession $session, ?int $expectedVersion = null): void
    {
        $stored = $this->sessions[$session->id()] ?? null;
        if ($stored === null && $expectedVersion !== null) {
            throw new CheckoutConflictException('La session attendue n’existe plus.', 'concurrent_update');
        }
        if ($stored !== null && $expectedVersion === null) {
            throw new CheckoutConflictException('La session existe déjà.', 'duplicate_checkout');
        }
        if ($stored !== null && $stored->version() !== $expectedVersion) {
            throw new CheckoutConflictException('La version de la session a changé.', 'concurrent_update');
        }

        $this->sessions[$session->id()] = clone $session;
    }

    public function recordTransition(CheckoutTransition $transition): void
    {
        $this->transitions[] = $transition;
    }

    public function hasProcessedCallback(string $eventId): bool
    {
        return isset($this->callbacks[$eventId]);
    }

    public function markCallbackProcessed(string $eventId, string $checkoutId): void
    {
        $this->callbacks[$eventId] = $checkoutId;
    }

    /** @return list<CheckoutTransition> */
    public function transitions(): array
    {
        return $this->transitions;
    }
}
