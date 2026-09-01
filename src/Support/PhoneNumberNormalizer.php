<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Support;

use Holduix\FlexPay\Exception\ValidationException;

final class PhoneNumberNormalizer
{
    public function normalize(string $phoneNumber): string
    {
        $normalized = preg_replace('/[^0-9]/', '', trim($phoneNumber)) ?? '';

        if (strlen($normalized) < 8 || strlen($normalized) > 15) {
            throw new ValidationException(
                'Numéro Mobile Money : utilisez un numéro international de 8 à 15 chiffres, par exemple 243…'
            );
        }

        return $normalized;
    }
}
