<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Enum;

enum NormalizedPaymentStatus: string
{
    case CREATED = 'created';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case REQUIRES_ACTION = 'requires_action';
    case SUCCESSFUL = 'successful';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case UNKNOWN = 'unknown';

    public function isFinal(): bool
    {
        return in_array($this, [self::SUCCESSFUL, self::FAILED, self::CANCELLED, self::EXPIRED], true);
    }

    public function isPending(): bool
    {
        return in_array($this, [self::CREATED, self::PENDING, self::PROCESSING, self::REQUIRES_ACTION], true);
    }
}
