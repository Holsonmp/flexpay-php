<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Policy;

use DateTimeImmutable;
use Holduix\FlexPay\Checkout\CheckoutSession;
use Holduix\FlexPay\Config\CheckoutConfiguration;

final class StatusRefreshPolicy
{
    public function __construct(private readonly CheckoutConfiguration $configuration)
    {
    }

    public function shouldRefresh(
        CheckoutSession $session,
        DateTimeImmutable $now,
        bool $force = false
    ): bool {
        if (
            $session->normalizedStatus()->isFinal()
            || ($session->providerTransactionId() === null && $session->providerTransactionCandidate() === null)
        ) {
            return false;
        }
        if ($force || $session->lastProviderRefreshAt() === null) {
            return true;
        }

        return $now->getTimestamp() - $session->lastProviderRefreshAt()->getTimestamp()
            >= $this->configuration->statusMinimumRefreshSeconds;
    }

    public function shouldRefreshCandidate(
        CheckoutSession $session,
        DateTimeImmutable $now,
        ?string $providerTransactionIdCandidate,
        bool $force = false
    ): bool {
        if ($session->providerTransactionId() !== null) {
            return $this->shouldRefresh($session, $now, $force);
        }
        if (
            $providerTransactionIdCandidate === null
            || trim($providerTransactionIdCandidate) === ''
            || $session->normalizedStatus()->isFinal()
        ) {
            return false;
        }
        if ($force || $session->lastProviderRefreshAt() === null) {
            return true;
        }

        return $now->getTimestamp() - $session->lastProviderRefreshAt()->getTimestamp()
            >= $this->configuration->statusMinimumRefreshSeconds;
    }

    public function nextPollAfterMilliseconds(CheckoutSession $session, DateTimeImmutable $now): int
    {
        $lastRefresh = $session->lastProviderRefreshAt();
        if ($lastRefresh === null) {
            return $this->configuration->pollInitialDelayMilliseconds;
        }
        $elapsed = max(0, $now->getTimestamp() - $lastRefresh->getTimestamp());
        $minimumRemaining = max(0, $this->configuration->statusMinimumRefreshSeconds - $elapsed) * 1000;
        $errorBackoff = $this->configuration->pollIntervalMilliseconds;
        for ($attempt = 0; $attempt < $session->consecutiveNetworkErrors(); ++$attempt) {
            $errorBackoff = min(
                $this->configuration->pollMaximumIntervalMilliseconds,
                $errorBackoff * 2
            );
        }

        return max($minimumRemaining, $errorBackoff);
    }

    public function shouldBecomeUnknownAfterNetworkErrors(CheckoutSession $session): bool
    {
        return $session->consecutiveNetworkErrors() >= $this->configuration->maximumNetworkErrors;
    }
}
