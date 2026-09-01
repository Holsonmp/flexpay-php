<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Mapper;

use Holduix\FlexPay\Checkout\CheckoutState;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;

final class CheckoutStatusMapper
{
    public function toCheckoutState(NormalizedPaymentStatus $status): CheckoutState
    {
        return match ($status) {
            NormalizedPaymentStatus::CREATED,
            NormalizedPaymentStatus::PENDING,
            NormalizedPaymentStatus::PROCESSING => CheckoutState::PENDING,
            NormalizedPaymentStatus::REQUIRES_ACTION => CheckoutState::REQUIRES_ACTION,
            NormalizedPaymentStatus::SUCCESSFUL => CheckoutState::SUCCESSFUL,
            NormalizedPaymentStatus::FAILED => CheckoutState::FAILED,
            NormalizedPaymentStatus::CANCELLED => CheckoutState::CANCELLED,
            NormalizedPaymentStatus::EXPIRED => CheckoutState::EXPIRED,
            NormalizedPaymentStatus::UNKNOWN => CheckoutState::UNKNOWN,
        };
    }
}
