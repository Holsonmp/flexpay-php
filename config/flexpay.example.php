<?php

declare(strict_types=1);

return [
    'api_base_url' => getenv('FLEXPAY_URL_API') ?: 'https://backend.flexpay.cd',
    'card_base_url' => getenv('FLEXPAY_URL_API_CARD') ?: 'https://cardpayment.flexpay.cd',
    'merchant_id' => getenv('FLEXPAY_MERCHANT') ?: '',
    'api_token' => getenv('FLEXPAY_TOKEN') ?: '',
    'callback_token' => getenv('FLEXPAY_CALLBACK_TOKEN') ?: '',
    'mobile_money_endpoint' => '/api/rest/v1/paymentService',
    'transaction_status_endpoint' => '/api/rest/v1/check',
    'card_order_status_endpoint' => '/api/rest/v1/vpos/orderNumber',
    'card_checkout_endpoint' => '/v3/pay',
    'default_currency' => 'CDF',
    'timeout_seconds' => 30,
    'checkout' => [
        'allowed_checkout_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', getenv('FLEXPAY_CARD_ALLOWED_CHECKOUT_HOSTS') ?: 'cardpayment.flexpay.cd')
        ))),
        'allow_insecure_checkout_urls' => false,
        'poll_initial_delay_milliseconds' => 0,
        'poll_interval_milliseconds' => 4000,
        'poll_maximum_interval_milliseconds' => 10000,
        'poll_timeout_seconds' => 180,
        'status_minimum_refresh_seconds' => 4,
        'maximum_network_errors' => 3,
    ],
];
