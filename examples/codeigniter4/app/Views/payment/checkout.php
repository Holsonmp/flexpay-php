<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);
?>

<link rel="stylesheet" href="<?= base_url('vendor/flexpay/flexpay-checkout.css') ?>">

<button
    type="button"
    id="pay-button"
    data-checkout-id="<?= esc($checkoutId) ?>"
>
    Payer
</button>

<script src="<?= base_url('vendor/flexpay/flexpay-checkout.iife.js') ?>" defer></script>
<script src="<?= base_url('assets/js/flexpay-checkout-page.js') ?>" defer></script>
