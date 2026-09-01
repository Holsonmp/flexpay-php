<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Integration;

use Holduix\FlexPay\Checkout\CheckoutManager;
use Holduix\FlexPay\Checkout\CheckoutState;
use Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutCallbackRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutReturnRequest;
use Holduix\FlexPay\Checkout\DTO\InitializeCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\RefreshCheckoutStatusRequest;
use Holduix\FlexPay\Checkout\Exception\CheckoutConflictException;
use Holduix\FlexPay\Checkout\Support\InMemoryCheckoutStore;
use Holduix\FlexPay\Config\CheckoutConfiguration;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\Exception\NetworkException;
use Holduix\FlexPay\Tests\Support\ClientFactory;
use Holduix\FlexPay\Tests\Support\FakeHttpClient;
use Holduix\FlexPay\Tests\Support\RecordingCheckoutListener;
use Holduix\FlexPay\Tests\Support\TestNetworkException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class CheckoutManagerTest extends TestCase
{
    public function testMobileMoneyFlowPendingThenSuccessfulAndStopsAtTerminal(): void
    {
        $success = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'FAKE-MM-ORDER-2026-0001',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $http = new FakeHttpClient([
            $this->responseFixture('mobile-money/initiation-pending.json'),
            $this->responseFixture('mobile-money/initiation-pending.json'),
            $this->responseFixture('mobile-money/initiation-pending.json'),
            new Response(200, ['Content-Type' => 'application/json'], $success),
        ]);
        [$manager, $store, $listener] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $initialized = $manager->initialize(new InitializeCheckoutRequest(
            $session->id(),
            PaymentMethod::MOBILE_MONEY,
            '+243 970 000 003'
        ));

        self::assertSame('pending', $initialized->checkout->status);
        self::assertSame('poll', $initialized->action->type->value);
        self::assertSame(CheckoutState::PENDING, $store->findById($session->id())?->state());

        $manager->refreshStatus(new RefreshCheckoutStatusRequest($session->id(), true));
        $manager->refreshStatus(new RefreshCheckoutStatusRequest($session->id(), true));
        $successful = $manager->refreshStatus(new RefreshCheckoutStatusRequest($session->id(), true));
        $cached = $manager->refreshStatus(new RefreshCheckoutStatusRequest($session->id(), true));

        self::assertSame('successful', $successful->checkout->status);
        self::assertTrue($successful->checkout->terminal);
        self::assertTrue($cached->idempotent);
        self::assertCount(4, $http->requests);
        self::assertContains(
            'PaymentSucceeded',
            array_map(static fn ($event): string => $event->name, $listener->events)
        );
    }

    public function testDoubleInitializationDoesNotCreateSecondProviderPayment(): void
    {
        $http = new FakeHttpClient([$this->responseFixture('mobile-money/initiation-pending.json')]);
        [$manager] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $request = new InitializeCheckoutRequest(
            $session->id(),
            PaymentMethod::MOBILE_MONEY,
            '+243970000003'
        );
        $manager->initialize($request);

        try {
            $manager->initialize($request);
            self::fail('La seconde initialisation devait être refusée.');
        } catch (CheckoutConflictException $exception) {
            self::assertSame('checkout_already_initialized', $exception->errorCode);
        }
        self::assertCount(1, $http->requests);
    }

    public function testCardIsPersistedBeforeRedirectAndReturnUsesServerVerification(): void
    {
        $success = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-0001',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $http = new FakeHttpClient([new Response(200, ['Content-Type' => 'application/json'], $success)]);
        [$manager, $store] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $initialized = $manager->initialize(new InitializeCheckoutRequest(
            $session->id(),
            PaymentMethod::CARD
        ));
        $persisted = $store->findById($session->id());

        self::assertSame('redirect', $initialized->action->type->value);
        self::assertNotNull($persisted);
        self::assertSame(CheckoutState::REDIRECTING, $persisted->state());
        self::assertNull($persisted->checkoutUrl());
        self::assertArrayNotHasKey('checkout_url', $persisted->toStorageArray());

        $verified = $manager->handleReturn(new HandleCheckoutReturnRequest(
            $session->id(),
            ['status' => 'failed', 'orderNumber' => 'CARD-0001']
        ));
        self::assertSame('successful', $verified->checkout->status);
    }

    public function testCallbackBeforeReturnIsVerifiedAndDuplicateIsIdempotent(): void
    {
        $success = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-CALLBACK-1',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $http = new FakeHttpClient([new Response(200, ['Content-Type' => 'application/json'], $success)]);
        [$manager] = $this->manager(
            $http,
            new CheckoutConfiguration(statusMinimumRefreshSeconds: 0)
        );
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));
        $body = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-CALLBACK-1',
            'status' => 'failed',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $request = new HandleCheckoutCallbackRequest(self::callbackHeaders(), $body);

        $first = $manager->handleCallback($request);
        $second = $manager->handleCallback($request);
        $returned = $manager->handleReturn(new HandleCheckoutReturnRequest($session->id()));

        self::assertSame('successful', $first->checkout->status);
        self::assertTrue($second->idempotent);
        self::assertTrue($returned->idempotent);
        self::assertCount(1, $http->requests);
    }

    public function testSemanticallyIdenticalCallbackWithExtraFieldsIsIdempotent(): void
    {
        $success = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-SEMANTIC-1',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $http = new FakeHttpClient([new Response(200, ['Content-Type' => 'application/json'], $success)]);
        [$manager, , $listener] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));
        $base = [
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-SEMANTIC-1',
            'status' => 'pending',
            'amount' => 50,
            'currency' => 'USD',
        ];

        $manager->handleCallback(new HandleCheckoutCallbackRequest(
            self::callbackHeaders(),
            json_encode($base, JSON_THROW_ON_ERROR)
        ));
        $duplicate = $manager->handleCallback(new HandleCheckoutCallbackRequest(
            self::callbackHeaders(),
            json_encode(['untrusted_extra' => bin2hex(random_bytes(8))] + $base, JSON_THROW_ON_ERROR)
        ));

        self::assertTrue($duplicate->idempotent);
        self::assertCount(1, $http->requests);
        self::assertCount(1, array_filter(
            $listener->events,
            static fn ($event): bool => $event->name === 'PaymentSucceeded'
        ));
    }

    public function testNetworkErrorsNeverBecomeFailedAndEventuallyBecomeUnknown(): void
    {
        $factory = new Psr17Factory();
        $networkRequest = $factory->createRequest('GET', 'https://backend.flexpay.cd/status');
        $http = new FakeHttpClient([
            $this->responseFixture('mobile-money/initiation-pending.json'),
            new TestNetworkException('offline 1', $networkRequest),
            new TestNetworkException('offline 2', $networkRequest),
            new TestNetworkException('offline 3', $networkRequest),
        ]);
        [$manager, $store] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest(
            $session->id(),
            PaymentMethod::MOBILE_MONEY,
            '243970000003'
        ));

        for ($attempt = 0; $attempt < 3; ++$attempt) {
            try {
                $manager->refreshStatus(new RefreshCheckoutStatusRequest($session->id(), true));
            } catch (NetworkException) {
                self::assertNotSame('failed', $store->findById($session->id())?->normalizedStatus()->value);
            }
        }

        self::assertSame(CheckoutState::UNKNOWN, $store->findById($session->id())?->state());
    }

    public function testUnverifiedCallbackCandidateSurvivesNetworkFailureAndThrottle(): void
    {
        $factory = new Psr17Factory();
        $networkRequest = $factory->createRequest('GET', 'https://backend.flexpay.cd/status');
        $success = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-RETRY-1',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $http = new FakeHttpClient([
            new TestNetworkException('offline', $networkRequest),
            new Response(200, ['Content-Type' => 'application/json'], $success),
        ]);
        $store = new InMemoryCheckoutStore();
        $now = new \DateTimeImmutable('2026-08-31T12:00:00+00:00');
        $manager = new CheckoutManager(
            ClientFactory::create($http),
            $store,
            configuration: new CheckoutConfiguration(statusMinimumRefreshSeconds: 4),
            clock: static function () use (&$now): \DateTimeImmutable {
                return $now;
            }
        );
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));
        $body = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-RETRY-1',
            'status' => 'pending',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $callback = new HandleCheckoutCallbackRequest(self::callbackHeaders(), $body);

        try {
            $manager->handleCallback($callback);
            self::fail('La première vérification devait signaler l’erreur réseau.');
        } catch (NetworkException) {
            $stored = $store->findById($session->id());
            self::assertNotNull($stored);
            self::assertNull($stored->providerTransactionId());
            self::assertSame('CARD-RETRY-1', $stored->providerTransactionCandidate());
        }

        $throttled = $manager->handleCallback($callback);
        self::assertFalse($throttled->idempotent);
        self::assertCount(1, $http->requests);

        $now = $now->modify('+5 seconds');
        $verified = $manager->handleCallback($callback);
        $duplicate = $manager->handleCallback($callback);

        self::assertSame('successful', $verified->checkout->status);
        self::assertTrue($duplicate->idempotent);
        self::assertCount(2, $http->requests);
    }

    public function testProviderResponseIdentifierMustMatchTheQueriedCandidate(): void
    {
        $body = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'OTHER-PROVIDER-ID',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $http = new FakeHttpClient([new Response(200, ['Content-Type' => 'application/json'], $body)]);
        [$manager, $store] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));

        $this->expectException(CheckoutConflictException::class);
        try {
            $manager->handleReturn(new HandleCheckoutReturnRequest(
                $session->id(),
                ['orderNumber' => 'QUERIED-PROVIDER-ID']
            ));
        } finally {
            self::assertNull($store->findById($session->id())?->providerTransactionId());
        }
    }

    public function testSparseSuccessfulCallbackNeverConfirmsPayment(): void
    {
        $http = new FakeHttpClient([new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"status":"success","orderNumber":"CARD-SPARSE-CALLBACK"}'
        )]);
        [$manager, $store] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));
        $callback = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-SPARSE-CALLBACK',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);

        $result = $manager->handleCallback(new HandleCheckoutCallbackRequest(self::callbackHeaders(), $callback));

        self::assertFalse($result->checkout->terminal);
        self::assertNotSame('successful', $result->checkout->status);
        self::assertNull($store->findById($session->id())?->providerTransactionId());
    }

    public function testCallbackAmountMismatchCannotConfirmPayment(): void
    {
        $http = new FakeHttpClient([]);
        [$manager] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));
        $body = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-1',
            'status' => 'success',
            'amount' => 49,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(CheckoutConflictException::class);
        $manager->handleCallback(new HandleCheckoutCallbackRequest(self::callbackHeaders(), $body));
    }

    public function testReturnBeforeCallbackCanRemainPendingThenBecomeSuccessful(): void
    {
        $pending = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-RETURN-1',
            'status' => 'pending',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $success = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-RETURN-1',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $http = new FakeHttpClient([
            new Response(200, ['Content-Type' => 'application/json'], $pending),
            new Response(200, ['Content-Type' => 'application/json'], $success),
        ]);
        [$manager] = $this->manager(
            $http,
            new CheckoutConfiguration(statusMinimumRefreshSeconds: 0)
        );
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));

        $returned = $manager->handleReturn(new HandleCheckoutReturnRequest(
            $session->id(),
            ['orderNumber' => 'CARD-RETURN-1', 'status' => 'success']
        ));
        self::assertSame('pending', $returned->checkout->status);
        self::assertFalse($returned->checkout->terminal);

        $callback = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-RETURN-1',
            'status' => 'pending',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $verified = $manager->handleCallback(new HandleCheckoutCallbackRequest(self::callbackHeaders(), $callback));
        self::assertSame('successful', $verified->checkout->status);
    }

    public function testFailedProviderStatusProducesTerminalFailed(): void
    {
        $failed = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-FAILED-1',
            'status' => 'failed',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $http = new FakeHttpClient([new Response(200, ['Content-Type' => 'application/json'], $failed)]);
        [$manager] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));

        $result = $manager->handleReturn(new HandleCheckoutReturnRequest(
            $session->id(),
            ['orderNumber' => 'CARD-FAILED-1']
        ));
        self::assertSame('failed', $result->checkout->status);
        self::assertTrue($result->checkout->terminal);
    }

    public function testSuccessfulSessionIgnoresLaterContradictoryCallback(): void
    {
        $success = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-FINAL-1',
            'status' => 'success',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $http = new FakeHttpClient([new Response(200, ['Content-Type' => 'application/json'], $success)]);
        [$manager, , $listener] = $this->manager($http);
        $session = $manager->create($this->createRequest());
        $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));
        $manager->handleReturn(new HandleCheckoutReturnRequest(
            $session->id(),
            ['orderNumber' => 'CARD-FINAL-1']
        ));

        $contradictory = json_encode([
            'merchant_reference' => 'ORDER-2026-0001',
            'orderNumber' => 'CARD-FINAL-1',
            'status' => 'failed',
            'amount' => 50,
            'currency' => 'USD',
        ], JSON_THROW_ON_ERROR);
        $result = $manager->handleCallback(new HandleCheckoutCallbackRequest(self::callbackHeaders(), $contradictory));

        self::assertSame('successful', $result->checkout->status);
        self::assertCount(1, array_filter(
            $listener->events,
            static fn ($event): bool => $event->name === 'PaymentSucceeded'
        ));
        self::assertCount(1, $http->requests);
    }

    public function testForbiddenMethodIsRejectedBeforeProviderCall(): void
    {
        $http = new FakeHttpClient([]);
        [$manager] = $this->manager($http);
        $request = $this->createRequest([PaymentMethod::MOBILE_MONEY]);
        $session = $manager->create($request);

        try {
            $manager->initialize(new InitializeCheckoutRequest($session->id(), PaymentMethod::CARD));
            self::fail('La carte devait être refusée.');
        } catch (\Holduix\FlexPay\Checkout\Exception\CheckoutException $exception) {
            self::assertSame('payment_method_not_allowed', $exception->errorCode);
        }
        self::assertCount(0, $http->requests);
    }

    /** @return array{CheckoutManager, InMemoryCheckoutStore, RecordingCheckoutListener} */
    private function manager(
        FakeHttpClient $http,
        ?CheckoutConfiguration $configuration = null
    ): array {
        $store = new InMemoryCheckoutStore();
        $listener = new RecordingCheckoutListener();

        return [
            new CheckoutManager(
                ClientFactory::create($http),
                $store,
                configuration: $configuration,
                eventListener: $listener
            ),
            $store,
            $listener,
        ];
    }

    /** @param list<PaymentMethod>|null $allowedMethods */
    private function createRequest(?array $allowedMethods = null): CreateCheckoutRequest
    {
        return new CreateCheckoutRequest(
            merchantReference: 'ORDER-2026-0001',
            amount: 50,
            currency: 'USD',
            allowedMethods: $allowedMethods ?? [PaymentMethod::MOBILE_MONEY, PaymentMethod::CARD],
            successUrl: 'https://example.com/payment/success',
            cancelUrl: 'https://example.com/payment/cancel',
            callbackUrl: 'https://example.com/payment/callback',
            customerName: 'Client Test',
            customerEmail: 'client@example.com'
        );
    }

    private function responseFixture(string $relativePath): Response
    {
        $fixture = file_get_contents(__DIR__ . '/../../fixtures/' . $relativePath);
        self::assertIsString($fixture);

        return new Response(200, ['Content-Type' => 'application/json'], $fixture);
    }

    /** @return array<string, string> */
    private static function callbackHeaders(): array
    {
        return ['X-FlexPay-Callback-Token' => 'callback-test'];
    }
}
