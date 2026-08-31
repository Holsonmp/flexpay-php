<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Service;

use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Response\CardPaymentResult;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\Support\Redactor;
use Holduix\FlexPay\Validation\RequestValidator;

final class CardPaymentService
{
    public function __construct(
        private readonly FlexPayConfiguration $configuration,
        private readonly RequestValidator $validator,
        private readonly Redactor $redactor
    ) {
    }

    public function createPayment(CardPaymentRequest $request): CardPaymentResult
    {
        $phone = $this->validator->validateCard($request);
        $currency = strtoupper(trim($request->currency));
        $token = $this->rawPortalToken();
        $query = [
            'authorization' => $token,
            'merchant' => $this->configuration->merchantId,
            'reference' => trim($request->merchantReference),
            'amount' => (string) $request->amount,
            'currency' => $currency,
            'description' => $request->description,
            'approve_url' => $request->approveUrl,
            'callback_url' => $request->callbackUrl,
            'cancel_url' => $request->cancelUrl,
            'decline_url' => $request->declineUrl,
            'home_url' => $request->homeUrl,
            'token' => $token,
            'merchant_code' => $token,
            'app_code' => $this->configuration->merchantId,
            'redirection' => '1',
            'payment_method' => $request->paymentMethod === PaymentMethod::PORTAL
                ? ''
                : $request->paymentMethod->value,
        ];

        if ($phone !== '') {
            $query['customer_phone'] = $phone;
            $query['customerPhone'] = $phone;
        }
        $query = array_filter($query, static fn (string $value): bool => trim($value) !== '');
        $endpoint = $this->resolveCheckoutEndpoint();
        $checkoutUrl = $endpoint . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $raw = $this->redactor->redact([
            '_source' => 'package_portal',
            '_http_code' => 200,
            'status' => 'pending',
            'reference' => trim($request->merchantReference),
            'merchant' => $this->configuration->merchantId,
            'amount' => $request->amount,
            'currency' => $currency,
            'description' => $request->description,
            'payment_url' => $checkoutUrl,
            'callback_url' => $request->callbackUrl,
            'approve_url' => $request->approveUrl,
            'cancel_url' => $request->cancelUrl,
            'decline_url' => $request->declineUrl,
            'home_url' => $request->homeUrl,
            'customer_phone' => $phone,
            'payment_method' => $request->paymentMethod->value,
        ]);

        return new CardPaymentResult(
            accepted: true,
            checkoutUrl: $checkoutUrl,
            normalizedStatus: NormalizedPaymentStatus::PENDING,
            rawStatus: 'pending',
            rawResponse: $raw,
            transactionReference: trim($request->merchantReference),
            providerTransactionId: null,
            paymentMethod: $request->paymentMethod,
            amount: $request->amount,
            currency: $currency,
            message: null,
            occurredAt: null
        );
    }

    private function rawPortalToken(): string
    {
        $token = trim($this->configuration->apiToken);
        return stripos($token, 'bearer ') === 0 ? trim(substr($token, 7)) : $token;
    }

    private function resolveCheckoutEndpoint(): string
    {
        if (preg_match('~^https?://~i', $this->configuration->cardCheckoutEndpoint) === 1) {
            return $this->configuration->cardCheckoutEndpoint;
        }

        return rtrim($this->configuration->cardBaseUrl, '/')
            . '/'
            . ltrim($this->configuration->cardCheckoutEndpoint, '/');
    }
}
