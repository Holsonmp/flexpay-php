<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Contract;

use Holduix\FlexPay\Contracts\HostedCardCheckoutInterface;
use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Response\CardPaymentResult;
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\Exception\UnsupportedOperationException;
use Holduix\FlexPay\Exception\ValidationException;
use Holduix\FlexPay\Tests\Support\ClientFactory;
use Holduix\FlexPay\Tests\Support\FakeHttpClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testHostedCardCheckoutIsDisabledWithoutAConfirmedBrowserSafeAdapter(): void
    {
        $client = ClientFactory::create(new FakeHttpClient([]), withFakeHostedCheckout: false);

        $this->expectException(UnsupportedOperationException::class);
        $client->card()->createPayment(new CardPaymentRequest(
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
    }

    public function testConfirmedHostedCardAdapterNeverReceivesTheServerBearer(): void
    {
        $result = ClientFactory::create(new FakeHttpClient([]))->card()->createPayment(new CardPaymentRequest(
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
        self::assertSame(NormalizedPaymentStatus::REQUIRES_ACTION, $result->normalizedStatus);
        self::assertStringNotContainsString('token-test', rawurldecode($result->checkoutUrl));
        parse_str((string) parse_url($result->checkoutUrl, PHP_URL_QUERY), $query);
        self::assertArrayNotHasKey('authorization', $query);
        self::assertArrayNotHasKey('token', $query);
        self::assertArrayNotHasKey('merchant_code', $query);
        self::assertArrayNotHasKey('card_number', $query);
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

    /** @return iterable<string, array{string}> */
    public static function leakingCheckoutUrls(): iterable
    {
        yield 'Bearer en clair' => ['https://cardpayment.flexpay.cd/pay?token=token-test'];
        yield 'Bearer doublement encodé' => [
            'https://cardpayment.flexpay.cd/pay?token=%2574oken-test',
        ];
        yield 'secret callback doublement encodé' => [
            'https://cardpayment.flexpay.cd/pay?callback=%2563allback-test',
        ];
    }

    #[DataProvider('leakingCheckoutUrls')]
    public function testHostedCardAdapterCannotLeakAServerSecret(string $checkoutUrl): void
    {
        $adapter = new class ($checkoutUrl) implements HostedCardCheckoutInterface {
            public function __construct(private readonly string $checkoutUrl)
            {
            }

            public function createCheckout(CardPaymentRequest $request, string $normalizedPhone): CardPaymentResult
            {
                return new CardPaymentResult(
                    accepted: true,
                    checkoutUrl: $this->checkoutUrl,
                    normalizedStatus: NormalizedPaymentStatus::REQUIRES_ACTION,
                    rawStatus: 'requires_action',
                    rawResponse: [],
                    transactionReference: $request->merchantReference,
                    providerTransactionId: null,
                    paymentMethod: $request->paymentMethod,
                    amount: $request->amount,
                    currency: $request->currency
                );
            }
        };

        $this->expectException(ValidationException::class);
        ClientFactory::create(
            new FakeHttpClient([]),
            hostedCardCheckout: $adapter
        )->card()->createPayment(new CardPaymentRequest(
            amount: 50,
            currency: 'USD',
            merchantReference: 'ORDER-LEAK',
            description: 'Commande ORDER-LEAK',
            callbackUrl: 'https://example.com/callback',
            approveUrl: 'https://example.com/approve',
            cancelUrl: 'https://example.com/cancel',
            declineUrl: 'https://example.com/decline',
            homeUrl: 'https://example.com',
            paymentMethod: PaymentMethod::CARD
        ));
    }
}
