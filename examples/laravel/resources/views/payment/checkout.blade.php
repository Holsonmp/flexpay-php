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

@vite([])
<link rel="stylesheet" href="{{ asset('vendor/flexpay/flexpay-checkout.css') }}">
<button type="button" id="pay-button" data-checkout-id="{{ $checkoutId }}">Payer</button>
<script src="{{ asset('vendor/flexpay/flexpay-checkout.iife.js') }}" defer></script>
<script src="{{ asset('js/flexpay-checkout-page.js') }}" defer></script>
