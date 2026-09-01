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

final class RetryConfiguration
{
    public function __construct(
        public readonly int $maxAttempts = 1,
        public readonly int $baseDelayMilliseconds = 200,
        public readonly int $maxDelayMilliseconds = 2000,
        public readonly bool $jitter = true
    ) {
        if ($maxAttempts < 1 || $maxAttempts > 5) {
            throw new ConfigurationException('Retry : maxAttempts doit être compris entre 1 et 5.');
        }
        if ($baseDelayMilliseconds < 0 || $maxDelayMilliseconds < $baseDelayMilliseconds) {
            throw new ConfigurationException('Retry : les délais configurés sont invalides.');
        }
    }
}
