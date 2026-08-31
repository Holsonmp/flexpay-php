<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
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
        $order = db_connect()->table('orders')->where('id', $orderId)->get()->getRow();
        if ($order === null) {
            return $this->failNotFound('Commande introuvable.');
        }
        $session = Services::flexPayCheckout()->create(new CreateCheckoutRequest(
            merchantReference: (string) $order->reference,
            amount: (float) $order->total_amount,
            currency: (string) $order->currency,
            allowedMethods: [PaymentMethod::MOBILE_MONEY, PaymentMethod::CARD],
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
        $result = Services::flexPayCheckout()->handleCallback(new HandleCheckoutCallbackRequest(
            $headers,
            (string) $this->request->getBody(),
            $this->request->getGet()
        ));
        return $this->respond(['acknowledged' => true, 'checkout_id' => $result->checkout->checkoutId]);
    }

    private function assertAuthorized(string $checkoutId): void
    {
        // Comparer ici le propriétaire de la commande et l’utilisateur authentifié.
        // Ne jamais autoriser une session uniquement parce que son identifiant est valide.
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
