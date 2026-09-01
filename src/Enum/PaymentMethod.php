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

enum PaymentMethod: string
{
    case MOBILE_MONEY = 'mobile_money';
    case CARD = 'card';
    case PORTAL = 'portal';
}
