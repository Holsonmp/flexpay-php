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

use Holduix\FlexPay\Exception\ConfigurationException;

final class CheckoutFeaturePolicy
{
    public function __construct(
        private readonly string $backendDriver = 'legacy',
        private readonly string $uiDriver = 'legacy',
        private readonly bool $allowPackageInProduction = false
    ) {
        $this->validateDriver($this->backendDriver, 'backend');
        $this->validateDriver($this->uiDriver, 'interface');
    }

    public function backendDriver(string $environment): string
    {
        return $this->packageAllowed($environment) ? $this->backendDriver : 'legacy';
    }

    public function uiDriver(string $environment): string
    {
        return $this->packageAllowed($environment) ? $this->uiDriver : 'legacy';
    }

    public function isPackageEnabled(string $environment): bool
    {
        return $this->backendDriver($environment) === 'package'
            || $this->uiDriver($environment) === 'package';
    }

    private function packageAllowed(string $environment): bool
    {
        return strtolower(trim($environment)) !== 'production' || $this->allowPackageInProduction;
    }

    private function validateDriver(string $driver, string $label): void
    {
        if (!in_array($driver, ['legacy', 'package'], true)) {
            throw new ConfigurationException(sprintf(
                'Checkout : le driver %s doit valoir legacy ou package.',
                $label
            ));
        }
    }
}
