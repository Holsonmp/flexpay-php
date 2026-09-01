<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Config;

use Holduix\FlexPay\Exception\ConfigurationException;

final class CheckoutConfiguration
{
    /**
     * @param list<string> $allowedCheckoutHosts
     */
    public function __construct(
        public readonly array $allowedCheckoutHosts = ['cardpayment.flexpay.cd'],
        public readonly bool $allowInsecureCheckoutUrls = false,
        public readonly int $pollInitialDelayMilliseconds = 0,
        public readonly int $pollIntervalMilliseconds = 4000,
        public readonly int $pollMaximumIntervalMilliseconds = 10000,
        public readonly int $pollTimeoutSeconds = 180,
        public readonly int $statusMinimumRefreshSeconds = 4,
        public readonly int $maximumNetworkErrors = 3,
        public readonly string $statusPathTemplate = '/payments/flexpay/checkouts/{checkoutId}/status'
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->allowedCheckoutHosts === []) {
            throw new ConfigurationException(
                'Checkout : au moins un hôte de redirection carte doit être autorisé.'
            );
        }

        foreach ($this->allowedCheckoutHosts as $host) {
            if (trim($host) === '' || preg_match('/^[a-z0-9.-]+$/i', $host) !== 1) {
                throw new ConfigurationException('Checkout : la liste des hôtes autorisés est invalide.');
            }
        }

        if ($this->pollInitialDelayMilliseconds < 0) {
            throw new ConfigurationException('Checkout : le délai initial de polling ne peut pas être négatif.');
        }
        if ($this->pollIntervalMilliseconds < 250) {
            throw new ConfigurationException(
                'Checkout : l’intervalle de polling doit être supérieur ou égal à 250 ms.'
            );
        }
        if ($this->pollMaximumIntervalMilliseconds < $this->pollIntervalMilliseconds) {
            throw new ConfigurationException(
                'Checkout : l’intervalle maximal doit être supérieur à l’intervalle initial.'
            );
        }
        if ($this->pollTimeoutSeconds < 1 || $this->statusMinimumRefreshSeconds < 0) {
            throw new ConfigurationException('Checkout : les durées de vérification sont invalides.');
        }
        if ($this->maximumNetworkErrors < 1) {
            throw new ConfigurationException('Checkout : au moins une erreur réseau doit être tolérée.');
        }
        if (!str_contains($this->statusPathTemplate, '{checkoutId}')) {
            throw new ConfigurationException('Checkout : le chemin de statut doit contenir {checkoutId}.');
        }
    }
}
