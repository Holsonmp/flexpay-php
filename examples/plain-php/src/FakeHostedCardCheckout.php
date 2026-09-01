<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Example;

use Holduix\FlexPay\Contracts\HostedCardCheckoutInterface;
use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Response\CardPaymentResult;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;

final class FakeHostedCardCheckout implements HostedCardCheckoutInterface
{
    public function createCheckout(CardPaymentRequest $request, string $normalizedPhone): CardPaymentResult
    {
        $identifier = 'FAKE-CARD-' . hash('sha256', $request->merchantReference);

        return new CardPaymentResult(
            accepted: true,
            checkoutUrl: 'https://cardpayment.flexpay.cd/fake-checkout?session=' . rawurlencode($identifier),
            normalizedStatus: NormalizedPaymentStatus::REQUIRES_ACTION,
            rawStatus: 'requires_action',
            rawResponse: ['status' => 'requires_action', 'fixture' => true],
            transactionReference: $request->merchantReference,
            providerTransactionId: null,
            paymentMethod: $request->paymentMethod,
            amount: $request->amount,
            currency: strtoupper($request->currency)
        );
    }
}
