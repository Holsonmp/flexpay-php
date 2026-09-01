<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/src/FakeFlexPayHttpClient.php';
require __DIR__ . '/src/FakeHostedCardCheckout.php';

use Example\FakeFlexPayHttpClient;
use Example\FakeHostedCardCheckout;
use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\FlexPayClient;
use Nyholm\Psr7\Factory\Psr17Factory;

$temporary = tempnam(sys_get_temp_dir(), 'flexpay-example-');
if ($temporary === false) {
    throw new RuntimeException('Impossible de créer le compteur temporaire.');
}

try {
    $factory = new Psr17Factory();
    $client = new FlexPayClient(
        new FlexPayConfiguration(
            apiBaseUrl: 'http://127.0.0.1/fake-flexpay',
            cardBaseUrl: 'https://cardpayment.flexpay.cd',
            merchantId: 'FAKE-MERCHANT',
            apiToken: 'fake-token',
            callbackToken: 'fake-callback-token',
            allowInsecureUrls: true
        ),
        new FakeFlexPayHttpClient($temporary),
        $factory,
        $factory,
        hostedCardCheckout: new FakeHostedCardCheckout()
    );
    $mobile = $client->mobileMoney()->createPayment(new MobileMoneyPaymentRequest(
        amount: 50,
        currency: 'USD',
        phoneNumber: '+243970000003',
        merchantReference: 'SMOKE-MM',
        callbackUrl: 'https://example.test/callback'
    ));
    if (!$mobile->accepted || $mobile->providerTransactionId === null) {
        throw new RuntimeException('Le smoke Mobile Money n’a pas produit de pending corrélable.');
    }
    $card = $client->card()->createPayment(new CardPaymentRequest(
        amount: 50,
        currency: 'USD',
        merchantReference: 'SMOKE-CARD',
        description: 'Carte fake SMOKE-CARD',
        callbackUrl: 'https://example.test/callback',
        approveUrl: 'https://example.test/success',
        cancelUrl: 'https://example.test/cancel',
        declineUrl: 'https://example.test/decline',
        homeUrl: 'https://example.test/',
        paymentMethod: PaymentMethod::CARD
    ));
    if (!str_starts_with($card->checkoutUrl, 'https://cardpayment.flexpay.cd/')) {
        throw new RuntimeException('Le smoke carte n’a pas produit une redirection sûre.');
    }
    $event = $client->webhooks()->parse(
        ['X-FlexPay-Callback-Token' => 'fake-callback-token'],
        '{"merchant_reference":"SMOKE-MM","orderNumber":"FAKE-SMOKE-MM","status":"pending"}'
    );
    if ($event->merchantReference !== 'SMOKE-MM') {
        throw new RuntimeException('Le smoke callback n’a pas retrouvé la référence.');
    }
} finally {
    @unlink($temporary);
}

fwrite(STDOUT, "Smoke PHP natif : Mobile Money, carte fake et callback validés.\n");
