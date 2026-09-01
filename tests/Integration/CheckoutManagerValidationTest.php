<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Integration;

use DateTimeImmutable;
use Holduix\FlexPay\Checkout\CheckoutManager;
use Holduix\FlexPay\Checkout\CheckoutState;
use Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutCallbackRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutReturnRequest;
use Holduix\FlexPay\Checkout\DTO\InitializeCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\RefreshCheckoutStatusRequest;
use Holduix\FlexPay\Checkout\Exception\CheckoutConflictException;
use Holduix\FlexPay\Checkout\Exception\CheckoutException;
use Holduix\FlexPay\Checkout\Exception\CheckoutNotFoundException;
use Holduix\FlexPay\Checkout\Support\InMemoryCheckoutStore;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\Exception\NetworkException;
use Holduix\FlexPay\Exception\ValidationException;
use Holduix\FlexPay\Tests\Support\ClientFactory;
use Holduix\FlexPay\Tests\Support\FakeHttpClient;
use Holduix\FlexPay\Tests\Support\TestNetworkException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckoutManagerValidationTest extends TestCase
{
    /** @return iterable<string, array{CreateCheckoutRequest, string}> */
    public static function invalidCreateRequests(): iterable
    {
        yield 'reference' => [self::request(merchantReference: ''), 'invalid_merchant_reference'];
        yield 'amount' => [self::request(amount: 0), 'invalid_checkout_amount'];
        yield 'currency' => [self::request(currency: 'US'), 'invalid_checkout_amount'];
        yield 'methods' => [self::request(allowedMethods: []), 'missing_payment_method'];
        yield 'url' => [self::request(successUrl: 'http://example.com'), 'invalid_checkout_url'];
    }

    #[DataProvider('invalidCreateRequests')]
    public function testInvalidSessionCreationIsRejected(
        CreateCheckoutRequest $request,
        string $errorCode
    ): void {
        [$manager] = $this->manager(new FakeHttpClient([]));

        try {
            $manager->create($request);
            self::fail('La création devait échouer.');
        } catch (CheckoutException $exception) {
            self::assertSame($errorCode, $exception->errorCode);
        }
    }

    public function testDuplicateMerchantReferenceIsRejected(): void
    {
        [$manager] = $this->manager(new FakeHttpClient([]));
        $manager->create(self::request());

        $this->expectException(CheckoutConflictException::class);
        $manager->create(self::request());
    }

    public function testUnknownSessionRaisesTypedException(): void
    {
        [$manager] = $this->manager(new FakeHttpClient([]));
        $this->expectException(CheckoutNotFoundException::class);
        $manager->details('chk_unknown');
    }

    public function testInvalidPhoneCanBeCorrectedWithoutDuplicateProviderCall(): void
    {
        $http = new FakeHttpClient([$this->response([
            'status' => 'pending',
            'reference' => 'ORDER-VALIDATION',
            'orderNumber' => 'MM-VALIDATION',
            'amount' => 50,
            'currency' => 'USD',
        ])]);
        [$manager, $store] = $this->manager($http);
        $session = $manager->create(self::request());

        try {
            $manager->initialize(new InitializeCheckoutRequest(
                $session->id(),
                PaymentMethod::MOBILE_MONEY,
                '123'
            ));
            self::fail('Le téléphone devait être refusé.');
        } catch (ValidationException) {
            self::assertSame(CheckoutState::COLLECTING_DETAILS, $store->findById($session->id())?->state());
        }

        $result = $manager->initialize(new InitializeCheckoutRequest(
            $session->id(),
            PaymentMethod::MOBILE_MONEY,
            '+243970000003'
        ));
        self::assertSame('pending', $result->checkout->status);
        self::assertCount(1, $http->requests);
    }

    public function testAmbiguousInitializationIsUnknownAndNeverRetried(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createRequest('POST', 'https://backend.flexpay.cd/payment');
        $http = new FakeHttpClient([new TestNetworkException('timeout', $request)]);
        [$manager, $store] = $this->manager($http);
        $session = $manager->create(self::request());

        try {
            $manager->initialize(new InitializeCheckoutRequest(
                $session->id(),
                PaymentMethod::MOBILE_MONEY,
                '+243970000003'
            ));
            self::fail('Le réseau devait échouer.');
        } catch (NetworkException) {
            self::assertSame(CheckoutState::UNKNOWN, $store->findById($session->id())?->state());
        }
        self::assertCount(1, $http->requests);
        $this->expectException(CheckoutConflictException::class);
        $manager->initialize(new InitializeCheckoutRequest(
            $session->id(),
            PaymentMethod::MOBILE_MONEY,
            '+243970000003'
        ));
    }

    public function testRecentLocalStatusAvoidsProviderRefresh(): void
    {
        $http = new FakeHttpClient([$this->response([
            'status' => 'pending',
            'reference' => 'ORDER-VALIDATION',
            'orderNumber' => 'MM-CACHE',
            'amount' => 50,
            'currency' => 'USD',
        ])]);
        [$manager] = $this->manager($http);
        $session = $manager->create(self::request());
        $manager->initialize(new InitializeCheckoutRequest(
            $session->id(),
            PaymentMethod::MOBILE_MONEY,
            '+243970000003'
        ));

        $cached = $manager->refreshStatus(new RefreshCheckoutStatusRequest($session->id()));
        self::assertTrue($cached->idempotent);
        self::assertCount(1, $http->requests);
    }

    public function testCardReturnWithoutProviderReferenceCannotConfirmPayment(): void
    {
        [$manager] = $this->manager(new FakeHttpClient([]));
        $session = $manager->create(self::request());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));

        $result = $manager->handleReturn(new HandleCheckoutReturnRequest(
            $session->id(),
            ['status' => 'success']
        ));
        self::assertSame('requires_action', $result->checkout->status);
        self::assertFalse($result->checkout->terminal);
    }

    public function testCallbackWithoutProviderReferenceIsOnlyASignalAndIsIdempotent(): void
    {
        [$manager] = $this->manager(new FakeHttpClient([]));
        $session = $manager->create(self::request());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));
        $body = json_encode([
            'merchant_reference' => 'ORDER-VALIDATION',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);

        $headers = ['X-FlexPay-Callback-Token' => 'callback-test'];
        $first = $manager->handleCallback(new HandleCheckoutCallbackRequest($headers, $body));
        $second = $manager->handleCallback(new HandleCheckoutCallbackRequest($headers, $body));
        self::assertNotSame('successful', $first->checkout->status);
        self::assertTrue($second->idempotent);
    }

    public function testProviderReferenceMismatchBecomesUnknownNotFailed(): void
    {
        $http = new FakeHttpClient([
            $this->response([
                'status' => 'pending',
                'reference' => 'ORDER-VALIDATION',
                'orderNumber' => 'MM-MISMATCH',
                'amount' => 50,
                'currency' => 'USD',
            ]),
            $this->response([
                'status' => 'success',
                'merchant_reference' => 'OTHER-ORDER',
                'orderNumber' => 'MM-MISMATCH',
                'amount' => 50,
                'currency' => 'USD',
            ]),
        ]);
        [$manager, $store] = $this->manager($http);
        $session = $manager->create(self::request());
        $manager->initialize(new InitializeCheckoutRequest(
            $session->id(),
            PaymentMethod::MOBILE_MONEY,
            '+243970000003'
        ));

        $this->expectException(CheckoutConflictException::class);
        try {
            $manager->refreshStatus(new RefreshCheckoutStatusRequest($session->id(), true));
        } finally {
            self::assertSame(CheckoutState::UNKNOWN, $store->findById($session->id())?->state());
        }
    }

    public function testSparseSuccessfulReturnNeverBindsAnUntrustedProviderReference(): void
    {
        $http = new FakeHttpClient([$this->response([
            'status' => 'success',
            'orderNumber' => 'FOREIGN-SUCCESS',
        ])]);
        [$manager, $store] = $this->manager($http);
        $session = $manager->create(self::request());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));

        $result = $manager->handleReturn(new HandleCheckoutReturnRequest(
            $session->id(),
            ['orderNumber' => 'FOREIGN-SUCCESS']
        ));
        $stored = $store->findById($session->id());

        self::assertNotNull($stored);
        self::assertNull($stored->providerTransactionId());
        self::assertSame('FOREIGN-SUCCESS', $stored->providerTransactionCandidate());
        self::assertNotSame(CheckoutState::SUCCESSFUL, $stored->state());
        self::assertFalse($result->checkout->terminal);
    }

    public function testSparseSuccessfulMobileInitializationRequiresAProviderRead(): void
    {
        $http = new FakeHttpClient([$this->response([
            'status' => 'success',
            'orderNumber' => 'UNVERIFIED-MOBILE-ID',
        ])]);
        [$manager, $store] = $this->manager($http);
        $session = $manager->create(self::request());

        $result = $manager->initialize(new InitializeCheckoutRequest(
            $session->id(),
            PaymentMethod::MOBILE_MONEY,
            '+243970000003'
        ));
        $stored = $store->findById($session->id());

        self::assertNotNull($stored);
        self::assertSame('unknown', $result->checkout->status);
        self::assertFalse($result->checkout->terminal);
        self::assertNull($stored->providerTransactionId());
        self::assertSame('UNVERIFIED-MOBILE-ID', $stored->providerTransactionCandidate());
        self::assertCount(1, $http->requests);
    }

    public function testExpiredSessionBecomesTerminalBeforeInitialization(): void
    {
        $now = new DateTimeImmutable('2026-08-31T12:00:00+00:00');
        $store = new InMemoryCheckoutStore();
        $manager = new CheckoutManager(
            ClientFactory::create(new FakeHttpClient([])),
            $store,
            clock: static fn (): DateTimeImmutable => $now
        );
        $session = $manager->create(self::request(expiresAt: $now->modify('+1 second')));
        $later = $now->modify('+2 seconds');
        $manager = new CheckoutManager(
            ClientFactory::create(new FakeHttpClient([])),
            $store,
            clock: static fn (): DateTimeImmutable => $later
        );

        $details = $manager->details($session->id());
        self::assertSame('expired', $details->status);
        self::assertTrue($details->terminal);
    }

    /** @return array{CheckoutManager, InMemoryCheckoutStore} */
    private function manager(FakeHttpClient $http): array
    {
        $store = new InMemoryCheckoutStore();
        return [new CheckoutManager(ClientFactory::create($http), $store), $store];
    }

    /** @param array<string, mixed> $payload */
    private function response(array $payload): Response
    {
        return new Response(
            200,
            ['Content-Type' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    /** @param list<PaymentMethod>|null $allowedMethods */
    private static function request(
        string $merchantReference = 'ORDER-VALIDATION',
        float $amount = 50,
        string $currency = 'USD',
        ?array $allowedMethods = null,
        string $successUrl = 'https://example.com/success',
        ?DateTimeImmutable $expiresAt = null
    ): CreateCheckoutRequest {
        return new CreateCheckoutRequest(
            merchantReference: $merchantReference,
            amount: $amount,
            currency: $currency,
            allowedMethods: $allowedMethods ?? [PaymentMethod::MOBILE_MONEY, PaymentMethod::CARD],
            successUrl: $successUrl,
            cancelUrl: 'https://example.com/cancel',
            callbackUrl: 'https://example.com/callback',
            expiresAt: $expiresAt
        );
    }
}
