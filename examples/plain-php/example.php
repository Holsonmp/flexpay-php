<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

// Démarrage CLI minimal. Pour le parcours HTTP complet, lancer :
// php -S 127.0.0.1:8080 -t public router.php
require __DIR__ . '/bootstrap.php';

use Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest;
use Holduix\FlexPay\Enum\PaymentMethod;

$session = $checkoutManager->create(new CreateCheckoutRequest(
    merchantReference: 'DEMO-' . date('YmdHis'),
    amount: 50,
    currency: 'USD',
    allowedMethods: [PaymentMethod::MOBILE_MONEY, PaymentMethod::CARD],
    successUrl: 'http://127.0.0.1:8080/payment/result',
    cancelUrl: 'http://127.0.0.1:8080/payment/cancel',
    callbackUrl: 'http://127.0.0.1:8080/payments/flexpay/callback'
));

echo "Checkout factice créé : {$session->id()}\n";
echo "Ce stockage fichier est une démonstration et ne convient pas à la production.\n";
