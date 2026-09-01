<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class FlexPayPackage extends BaseConfig
{
    public string $apiBaseUrl = 'https://backend.flexpay.cd';
    public string $cardBaseUrl = 'https://cardpayment.flexpay.cd';
    public string $merchantId = '';
    public string $apiToken = '';
    public string $callbackToken = '';
    /** @var list<string> */
    public array $allowedCheckoutHosts = ['cardpayment.flexpay.cd'];

    public function __construct()
    {
        parent::__construct();
        $this->merchantId = (string) env('FLEXPAY_MERCHANT', '');
        $this->apiToken = (string) env('FLEXPAY_TOKEN', '');
        $this->callbackToken = (string) env('FLEXPAY_CALLBACK_TOKEN', '');
        $this->allowedCheckoutHosts = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FLEXPAY_CARD_ALLOWED_CHECKOUT_HOSTS', 'cardpayment.flexpay.cd'))
        )));
    }
}
