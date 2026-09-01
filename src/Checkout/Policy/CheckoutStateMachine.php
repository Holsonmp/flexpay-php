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

use Holduix\FlexPay\Checkout\CheckoutState;
use Holduix\FlexPay\Checkout\Exception\InvalidCheckoutTransitionException;

final class CheckoutStateMachine
{
    /** @var array<string, list<CheckoutState>> */
    private const TRANSITIONS = [
        'created' => [CheckoutState::CHOOSING_METHOD, CheckoutState::EXPIRED],
        'choosing_method' => [
            CheckoutState::COLLECTING_DETAILS,
            CheckoutState::INITIALIZING,
            CheckoutState::EXPIRED,
        ],
        'collecting_details' => [
            CheckoutState::CHOOSING_METHOD,
            CheckoutState::INITIALIZING,
            CheckoutState::EXPIRED,
        ],
        'initializing' => [
            CheckoutState::PENDING,
            CheckoutState::REQUIRES_ACTION,
            CheckoutState::SUCCESSFUL,
            CheckoutState::FAILED,
            CheckoutState::CANCELLED,
            CheckoutState::EXPIRED,
            CheckoutState::UNKNOWN,
            CheckoutState::VERIFYING,
            CheckoutState::COLLECTING_DETAILS,
            CheckoutState::CHOOSING_METHOD,
        ],
        'pending' => [CheckoutState::VERIFYING, CheckoutState::EXPIRED],
        'requires_action' => [CheckoutState::REDIRECTING, CheckoutState::VERIFYING, CheckoutState::EXPIRED],
        'redirecting' => [CheckoutState::VERIFYING, CheckoutState::EXPIRED],
        'verifying' => [
            CheckoutState::PENDING,
            CheckoutState::REQUIRES_ACTION,
            CheckoutState::SUCCESSFUL,
            CheckoutState::FAILED,
            CheckoutState::CANCELLED,
            CheckoutState::EXPIRED,
            CheckoutState::UNKNOWN,
        ],
        'unknown' => [CheckoutState::VERIFYING, CheckoutState::PENDING, CheckoutState::EXPIRED],
    ];

    public function assertAllowed(CheckoutState $from, CheckoutState $to): void
    {
        if ($from === $to) {
            return;
        }
        if (!in_array($to, self::TRANSITIONS[$from->value] ?? [], true)) {
            throw new InvalidCheckoutTransitionException($from, $to);
        }
    }

    public function canTransition(CheckoutState $from, CheckoutState $to): bool
    {
        try {
            $this->assertAllowed($from, $to);
            return true;
        } catch (InvalidCheckoutTransitionException) {
            return false;
        }
    }
}
