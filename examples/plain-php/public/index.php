<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

session_start();
require dirname(__DIR__) . '/bootstrap.php';

use Holduix\FlexPay\Checkout\CheckoutPublicView;
use Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutCallbackRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutReturnRequest;
use Holduix\FlexPay\Checkout\DTO\InitializeCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\RefreshCheckoutStatusRequest;
use Holduix\FlexPay\Checkout\Exception\CheckoutException;
use Holduix\FlexPay\Checkout\Http\CheckoutResponseSerializer;
use Holduix\FlexPay\Enum\PaymentMethod;

$serializer = new CheckoutResponseSerializer();
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/') {
    require __DIR__ . '/checkout.php';
    return;
}

header('Content-Type: application/json; charset=utf-8');
try {
    if ($path === '/demo/create' && $method === 'POST') {
        $session = $checkoutManager->create(new CreateCheckoutRequest(
            merchantReference: 'DEMO-' . date('YmdHis') . '-' . bin2hex(random_bytes(2)),
            amount: 50,
            currency: 'USD',
            allowedMethods: [PaymentMethod::MOBILE_MONEY, PaymentMethod::CARD],
            successUrl: 'http://127.0.0.1:8080/payment/result',
            cancelUrl: 'http://127.0.0.1:8080/payment/cancel',
            callbackUrl: 'http://127.0.0.1:8080/payments/flexpay/callback'
        ));
        $_SESSION['checkout_id'] = $session->id();
        http_response_code(201);
        echo json_encode($serializer->details(CheckoutPublicView::fromSession($session)), JSON_THROW_ON_ERROR);
        return;
    }
    if ($path === '/payments/flexpay/callback' && $method === 'POST') {
        $checkoutManager->handleCallback(new HandleCheckoutCallbackRequest(
            function_exists('getallheaders') ? getallheaders() : [],
            (string) file_get_contents('php://input'),
            $_GET
        ));
        echo json_encode(['acknowledged' => true], JSON_THROW_ON_ERROR);
        return;
    }
    if (preg_match('#^/payments/flexpay/checkouts/([^/]+)(?:/(initialize|status|return))?$#', $path, $matches) !== 1) {
        http_response_code(404);
        echo json_encode(['message' => 'Route inconnue.'], JSON_THROW_ON_ERROR);
        return;
    }
    $checkoutId = rawurldecode($matches[1]);
    if (!hash_equals((string) ($_SESSION['checkout_id'] ?? ''), $checkoutId)) {
        http_response_code(403);
        echo json_encode(['message' => 'Session non accessible.'], JSON_THROW_ON_ERROR);
        return;
    }
    $operation = $matches[2] ?? '';
    if ($operation === 'initialize' && $method === 'POST') {
        $payload = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        $result = $checkoutManager->initialize(new InitializeCheckoutRequest(
            $checkoutId,
            PaymentMethod::from((string) ($payload['payment_method'] ?? '')),
            (string) ($payload['phone_number'] ?? '')
        ));
        http_response_code($result->checkout->terminal ? 200 : 202);
        echo json_encode($serializer->operation($result), JSON_THROW_ON_ERROR);
        return;
    }
    if ($operation === 'status' && $method === 'GET') {
        echo json_encode($serializer->operation(
            $checkoutManager->refreshStatus(new RefreshCheckoutStatusRequest($checkoutId))
        ), JSON_THROW_ON_ERROR);
        return;
    }
    if ($operation === 'return' && $method === 'GET') {
        echo json_encode($serializer->operation(
            $checkoutManager->handleReturn(new HandleCheckoutReturnRequest($checkoutId, $_GET))
        ), JSON_THROW_ON_ERROR);
        return;
    }
    echo json_encode($serializer->details($checkoutManager->details($checkoutId)), JSON_THROW_ON_ERROR);
} catch (CheckoutException $exception) {
    http_response_code($exception->errorCode === 'checkout_not_found' ? 404 : 409);
    echo json_encode($serializer->error($exception), JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'terminal' => false,
        'message' => 'Le fournisseur factice est indisponible.',
        'error' => ['code' => 'provider_unavailable'],
    ], JSON_THROW_ON_ERROR);
}
