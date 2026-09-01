<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Services;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutCallbackRequest;
use Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutReturnRequest;
use Holduix\FlexPay\Checkout\DTO\InitializeCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\RefreshCheckoutStatusRequest;
use Holduix\FlexPay\Checkout\Exception\CheckoutException;
use Holduix\FlexPay\Checkout\Http\CheckoutResponseSerializer;
use Holduix\FlexPay\Enum\PaymentMethod;

final class FlexPayCheckoutController extends ResourceController
{
    private CheckoutResponseSerializer $serializer;

    public function __construct()
    {
        $this->serializer = new CheckoutResponseSerializer();
    }

    public function createForOrder(int $orderId)
    {
        // Le montant, la devise et la référence proviennent exclusivement de la commande serveur.
        $order = $this->authorizedOrder($orderId);
        $session = Services::flexPayCheckout()->create(new CreateCheckoutRequest(
            merchantReference: (string) $order->reference,
            amount: (float) $order->total_amount,
            currency: (string) $order->currency,
            // La carte n’est ajoutée qu’après injection d’un adaptateur hébergé confirmé.
            allowedMethods: [PaymentMethod::MOBILE_MONEY],
            successUrl: site_url('payments/flexpay/result'),
            cancelUrl: site_url('payments/flexpay/cancel'),
            callbackUrl: site_url('payments/flexpay/callback'),
            customerName: (string) $order->customer_name,
            customerEmail: (string) $order->customer_email
        ));

        return $this->respondCreated($this->serializer->details(
            \Holduix\FlexPay\Checkout\CheckoutPublicView::fromSession($session)
        ));
    }

    public function details(string $checkoutId)
    {
        $this->assertAuthorized($checkoutId);
        return $this->respond($this->serializer->details(Services::flexPayCheckout()->details($checkoutId)));
    }

    public function initialize(string $checkoutId)
    {
        $this->assertAuthorized($checkoutId);
        $payload = $this->request->getJSON(true) ?: [];
        try {
            $result = Services::flexPayCheckout()->initialize(new InitializeCheckoutRequest(
                $checkoutId,
                PaymentMethod::from((string) ($payload['payment_method'] ?? '')),
                (string) ($payload['phone_number'] ?? '')
            ));
            return $this->respond($this->serializer->operation($result), $result->checkout->terminal ? 200 : 202);
        } catch (CheckoutException $exception) {
            return $this->respond($this->serializer->error($exception), $this->statusFor($exception));
        }
    }

    public function status(string $checkoutId)
    {
        $this->assertAuthorized($checkoutId);
        $result = Services::flexPayCheckout()->refreshStatus(new RefreshCheckoutStatusRequest($checkoutId));
        return $this->respond($this->serializer->operation($result));
    }

    public function paymentReturn(string $checkoutId)
    {
        $this->assertAuthorized($checkoutId);
        $result = Services::flexPayCheckout()->handleReturn(new HandleCheckoutReturnRequest(
            $checkoutId,
            $this->request->getGet()
        ));
        // La page de résultat recharge uniquement la vue publique filtrée.
        return redirect()->to('/payment/result?checkout_id=' . rawurlencode($result->checkout->checkoutId));
    }

    public function callback()
    {
        $headers = [];
        foreach ($this->request->headers() as $name => $header) {
            $headers[$name] = $header->getValueLine();
        }
        Services::flexPayCheckout()->handleCallback(new HandleCheckoutCallbackRequest(
            $headers,
            (string) $this->request->getBody(),
            $this->request->getGet()
        ));
        return $this->respond(['acknowledged' => true]);
    }

    private function assertAuthorized(string $checkoutId): void
    {
        $session = Services::flexPayCheckoutStore()->findById($checkoutId);
        $userId = session()->get('user_id');
        $order = $session === null || !is_numeric($userId)
            ? null
            : db_connect()->table('orders')
                ->where('reference', $session->merchantReference())
                ->where('user_id', (int) $userId)
                ->get()
                ->getRow();
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }
    }

    private function authorizedOrder(int $orderId): object
    {
        $userId = session()->get('user_id');
        $order = !is_numeric($userId)
            ? null
            : db_connect()->table('orders')
                ->where('id', $orderId)
                ->where('user_id', (int) $userId)
                ->get()
                ->getRow();
        if ($order === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $order;
    }

    private function statusFor(CheckoutException $exception): int
    {
        return match ($exception->errorCode) {
            'checkout_not_found' => 404,
            'checkout_already_initialized', 'checkout_conflict', 'concurrent_update' => 409,
            'payment_method_not_allowed', 'invalid_phone_number' => 422,
            default => 400,
        };
    }
}
