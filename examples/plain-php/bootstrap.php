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
require __DIR__ . '/src/FileCheckoutStore.php';
require __DIR__ . '/src/FakeFlexPayHttpClient.php';
require __DIR__ . '/src/FakeHostedCardCheckout.php';

use Example\FakeFlexPayHttpClient;
use Example\FileCheckoutStore;
use Example\FakeHostedCardCheckout;
use Holduix\FlexPay\Checkout\CheckoutManager;
use Holduix\FlexPay\Config\CheckoutConfiguration;
use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\FlexPayClient;
use Nyholm\Psr7\Factory\Psr17Factory;

$factory = new Psr17Factory();
$client = new FlexPayClient(
    new FlexPayConfiguration(
        apiBaseUrl: getenv('FLEXPAY_URL_API') ?: 'http://127.0.0.1:8080/fake-flexpay',
        cardBaseUrl: getenv('FLEXPAY_URL_API_CARD') ?: 'https://cardpayment.flexpay.cd',
        merchantId: getenv('FLEXPAY_MERCHANT') ?: 'FAKE-MERCHANT',
        apiToken: getenv('FLEXPAY_TOKEN') ?: 'fake-token',
        callbackToken: getenv('FLEXPAY_CALLBACK_TOKEN') ?: 'fake-callback-token',
        allowInsecureUrls: true
    ),
    new FakeFlexPayHttpClient(__DIR__ . '/var/fake-provider.json'),
    $factory,
    $factory,
    hostedCardCheckout: new FakeHostedCardCheckout()
);
$checkoutManager = new CheckoutManager(
    $client,
    new FileCheckoutStore(__DIR__ . '/var/checkouts.json'),
    configuration: new CheckoutConfiguration(
        allowedCheckoutHosts: ['cardpayment.flexpay.cd'],
        allowInsecureCheckoutUrls: true
    )
);
