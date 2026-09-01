<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout;

enum CheckoutActionType: string
{
    case NONE = 'none';
    case POLL = 'poll';
    case REDIRECT = 'redirect';
}
