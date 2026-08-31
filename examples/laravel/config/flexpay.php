<?php

declare(strict_types=1);

return [
    'api_base_url' => env('FLEXPAY_URL_API', 'https://backend.flexpay.cd'),
    'card_base_url' => env('FLEXPAY_URL_API_CARD', 'https://cardpayment.flexpay.cd'),
    'merchant_id' => env('FLEXPAY_MERCHANT'),
    'api_token' => env('FLEXPAY_TOKEN'),
    'callback_token' => env('FLEXPAY_CALLBACK_TOKEN', ''),
    'driver' => env('FLEXPAY_DRIVER', 'legacy'),
    'checkout_ui' => env('FLEXPAY_CHECKOUT_UI', 'legacy'),
    'allow_package_in_production' => env('FLEXPAY_ALLOW_PACKAGE_IN_PRODUCTION', false),
    'allowed_checkout_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('FLEXPAY_CARD_ALLOWED_CHECKOUT_HOSTS', 'cardpayment.flexpay.cd'))
    ))),
    'poll_initial_delay_ms' => (int) env('FLEXPAY_CHECKOUT_POLL_INITIAL_DELAY_MS', 0),
    'poll_interval_ms' => (int) env('FLEXPAY_CHECKOUT_POLL_INTERVAL_MS', 4000),
    'poll_max_interval_ms' => (int) env('FLEXPAY_CHECKOUT_POLL_MAX_INTERVAL_MS', 10000),
    'poll_timeout_seconds' => (int) env('FLEXPAY_CHECKOUT_POLL_TIMEOUT_SECONDS', 180),
    'status_min_refresh_seconds' => (int) env('FLEXPAY_STATUS_MIN_REFRESH_SECONDS', 4),
];
