<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Contract;

use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\Tests\Support\ClientFactory;
use Holduix\FlexPay\Tests\Support\FakeHttpClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class PaymentContractTest extends TestCase
{
    public function testMobileMoneyPayloadMatchesLegacyContract(): void
    {
        $fixture = file_get_contents(__DIR__ . '/../../fixtures/mobile-money/initiation-pending.json');
        self::assertIsString($fixture);
        $http = new FakeHttpClient([new Response(200, ['Content-Type' => 'application/json'], $fixture)]);
        $client = ClientFactory::create($http);

        $result = $client->mobileMoney()->createPayment(new MobileMoneyPaymentRequest(
            amount: 50,
            currency: 'usd',
            phoneNumber: '+243 970 000 003',
            merchantReference: 'ORDER-2026-0001',
            callbackUrl: 'https://example.com/payment/callback'
        ));

        self::assertTrue($result->accepted);
        self::assertSame(NormalizedPaymentStatus::PENDING, $result->normalizedStatus);
        self::assertSame('FAKE-MM-ORDER-2026-0001', $result->providerTransactionId);
        self::assertCount(1, $http->requests);
        $request = $http->requests[0];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://backend.flexpay.cd/api/rest/v1/paymentService', (string) $request->getUri());
        self::assertSame('Bearer token-test', $request->getHeaderLine('Authorization'));
        $payload = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            'merchant' => 'MERCHANT-TEST',
            'type' => 1,
            'reference' => 'ORDER-2026-0001',
            'phone' => '243970000003',
            'amount' => 50,
            'currency' => 'USD',
            'callbackUrl' => 'https://example.com/payment/callback',
        ], $payload);
    }

    public function testProviderReferenceIsNotMutatedByRawResponseRedaction(): void
    {
        $http = new FakeHttpClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'pending',
                'orderNumber' => '243970000003',
                'amount' => 50,
                'currency' => 'USD',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = ClientFactory::create($http)->mobileMoney()->createPayment(
            new MobileMoneyPaymentRequest(
                50,
                'USD',
                '+243970000003',
                'ORDER-NUMERIC-PROVIDER-REFERENCE',
                'https://example.com/payment/callback'
            )
        );

        self::assertSame('243970000003', $result->providerTransactionId);
        self::assertNotSame('243970000003', $result->rawResponse['orderNumber']);
    }

    public function testStatusReferencesRemainUsableWhileRawResponseIsRedacted(): void
    {
        $http = new FakeHttpClient([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'success',
                'reference' => '243970000004',
                'orderNumber' => '243970000003',
                'amount' => 50,
                'currency' => 'USD',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = ClientFactory::create($http)->transactions()->getStatus('243970000003');

        self::assertSame('243970000003', $result->providerTransactionId);
        self::assertSame('243970000004', $result->transactionReference);
        self::assertNotSame('243970000003', $result->rawResponse['orderNumber']);
        self::assertNotSame('243970000004', $result->rawResponse['reference']);
    }

    public function testHostedCardCheckoutMatchesLegacyQueryContractWithoutRawCardData(): void
    {
        $client = ClientFactory::create(new FakeHttpClient([]));
        $result = $client->card()->createPayment(new CardPaymentRequest(
            amount: 50,
            currency: 'USD',
            merchantReference: 'ORDER-2026-0003',
            description: 'Commande ORDER-2026-0003',
            callbackUrl: 'https://example.com/callback',
            approveUrl: 'https://example.com/approve',
            cancelUrl: 'https://example.com/cancel',
            declineUrl: 'https://example.com/decline',
            homeUrl: 'https://example.com',
            customerPhone: '+243970000003',
            paymentMethod: PaymentMethod::CARD
        ));

        self::assertTrue($result->accepted);
        self::assertSame(NormalizedPaymentStatus::PENDING, $result->normalizedStatus);
        self::assertNull($result->providerTransactionId);
        parse_str((string) parse_url($result->checkoutUrl, PHP_URL_QUERY), $query);
        self::assertSame('token-test', $query['authorization']);
        self::assertSame('token-test', $query['token']);
        self::assertSame('token-test', $query['merchant_code']);
        self::assertSame('MERCHANT-TEST', $query['merchant']);
        self::assertSame('MERCHANT-TEST', $query['app_code']);
        self::assertSame('card', $query['payment_method']);
        self::assertSame('243970000003', $query['customer_phone']);
        self::assertArrayNotHasKey('card_number', $query);
        $redactedUrl = $result->rawResponse['payment_url'] ?? null;
        self::assertIsString($redactedUrl);
        self::assertStringNotContainsString('token-test', $redactedUrl);
    }

    public function testPortalModeOmitsForcedPaymentMethod(): void
    {
        $client = ClientFactory::create(new FakeHttpClient([]));
        $result = $client->card()->createPayment(new CardPaymentRequest(
            10,
            'CDF',
            'ORDER-PORTAL',
            'Commande portail',
            'https://example.com/callback',
            'https://example.com/approve',
            'https://example.com/cancel',
            'https://example.com/decline',
            'https://example.com',
            paymentMethod: PaymentMethod::PORTAL
        ));
        parse_str((string) parse_url($result->checkoutUrl, PHP_URL_QUERY), $query);

        self::assertArrayNotHasKey('payment_method', $query);
    }
}
