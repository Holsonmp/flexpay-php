<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Contracts;

use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Response\CardPaymentResult;

/**
 * Adaptateur d’un contrat fournisseur confirmé pour un checkout carte hébergé.
 *
 * L’implémentation ne doit jamais remettre au navigateur le token Bearer utilisé
 * par les appels serveur. Elle doit retourner uniquement une capacité de checkout
 * explicitement documentée comme publique et limitée par le fournisseur.
 */
interface HostedCardCheckoutInterface
{
    public function createCheckout(CardPaymentRequest $request, string $normalizedPhone): CardPaymentResult;
}
