<?php

declare(strict_types=1);

$routes->post('orders/(:num)/flexpay-checkout', 'FlexPayCheckoutController::createForOrder/$1');
$routes->get('payments/flexpay/checkouts/(:segment)', 'FlexPayCheckoutController::details/$1');
$routes->post('payments/flexpay/checkouts/(:segment)/initialize', 'FlexPayCheckoutController::initialize/$1');
$routes->get(
    'payments/flexpay/checkouts/(:segment)/status',
    'FlexPayCheckoutController::status/$1',
    ['filter' => 'throttle:flexpay-status']
);
$routes->get('payments/flexpay/checkouts/(:segment)/return', 'FlexPayCheckoutController::paymentReturn/$1');
$routes->post('payments/flexpay/callback', 'FlexPayCheckoutController::callback');

// Route legacy conservée pendant la migration et désactivée après validation fournisseur.
$routes->post('payment/flexpay/callback', 'FlexPayCallbackController::callback');
