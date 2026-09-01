<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Support;

use Holduix\FlexPay\Contracts\HostedCardCheckoutInterface;
use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Response\CardPaymentResult;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;

final class FakeHostedCardCheckout implements HostedCardCheckoutInterface
{
    public function createCheckout(CardPaymentRequest $request, string $normalizedPhone): CardPaymentResult
    {
        $query = [
            'session' => 'fake_' . hash('sha256', $request->merchantReference),
            'reference' => $request->merchantReference,
        ];
        if ($request->paymentMethod !== PaymentMethod::PORTAL) {
            $query['payment_method'] = $request->paymentMethod->value;
        }
        if ($normalizedPhone !== '') {
            $query['customer_phone'] = $normalizedPhone;
        }

        return new CardPaymentResult(
            accepted: true,
            checkoutUrl: 'https://cardpayment.flexpay.cd/fake-checkout?'
                . http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            normalizedStatus: NormalizedPaymentStatus::REQUIRES_ACTION,
            rawStatus: 'requires_action',
            rawResponse: ['fixture' => 'hosted_card_checkout'],
            transactionReference: trim($request->merchantReference),
            providerTransactionId: null,
            paymentMethod: $request->paymentMethod,
            amount: $request->amount,
            currency: strtoupper(trim($request->currency))
        );
    }
}
