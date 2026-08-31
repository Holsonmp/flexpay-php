<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\Config\RetryConfiguration;
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\Exception\ApiException;
use Holduix\FlexPay\Exception\AuthorizationException;
use Holduix\FlexPay\Exception\RateLimitException;
use Holduix\FlexPay\FlexPayClient;
use Holduix\FlexPay\Tests\Support\ClientFactory;
use Holduix\FlexPay\Tests\Support\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpStatusMatrixTest extends TestCase
{
    /** @return iterable<string, array{int}> */
    public static function successfulStatuses(): iterable
    {
        yield '200' => [200];
        yield '201' => [201];
        yield '202' => [202];
    }

    /** @return iterable<string, array{int}> */
    public static function apiErrorStatuses(): iterable
    {
        yield '400' => [400];
        yield '404' => [404];
        yield '409' => [409];
        yield '422' => [422];
        yield '500' => [500];
        yield '502' => [502];
        yield '503' => [503];
        yield '504' => [504];
    }

    #[DataProvider('successfulStatuses')]
    public function testSuccessfulStatusCodesAreAcceptedForRead(int $statusCode): void
    {
        $client = ClientFactory::create(new FakeHttpClient([
            new Response($statusCode, [], '{"orderNumber":"ORDER-1","status":"pending"}'),
        ]));

        self::assertSame('pending', $client->transactions()->getStatus('ORDER-1')->normalizedStatus->value);
    }

    #[DataProvider('apiErrorStatuses')]
    public function testApiErrorStatusCodesAreTyped(int $statusCode): void
    {
        $this->expectException(ApiException::class);
        ClientFactory::create(new FakeHttpClient([
            new Response($statusCode, [], '{"code":"TEST","message":"Erreur simulée"}'),
        ]))->transactions()->getStatus('ORDER-1');
    }

    public function test403IsAuthorizationError(): void
    {
        $this->expectException(AuthorizationException::class);
        ClientFactory::create(new FakeHttpClient([
            new Response(403, [], '{"message":"Interdit"}'),
        ]))->transactions()->getStatus('ORDER-1');
    }

    public function test429IsRateLimitError(): void
    {
        $this->expectException(RateLimitException::class);
        ClientFactory::create(new FakeHttpClient([
            new Response(429, [], '{"message":"Limite atteinte"}'),
        ]))->transactions()->getStatus('ORDER-1');
    }

    public function testSafeReadCanRetry503ButCreationDoesNotRetry(): void
    {
        $http = new FakeHttpClient([
            new Response(503, [], '{"message":"Indisponible"}'),
            new Response(200, [], '{"orderNumber":"ORDER-1","status":"success"}'),
        ]);
        $factory = new Psr17Factory();
        $client = new FlexPayClient(
            new FlexPayConfiguration(
                'https://backend.flexpay.cd',
                'https://cardpayment.flexpay.cd',
                'M1',
                'token',
                retry: new RetryConfiguration(2, 0, 0, false)
            ),
            $http,
            $factory,
            $factory
        );

        self::assertSame('successful', $client->transactions()->getStatus('ORDER-1')->normalizedStatus->value);
        self::assertCount(2, $http->requests);
    }

    public function testPartialMobileResponseDoesNotPretendAcceptance(): void
    {
        $http = new FakeHttpClient([
            new Response(202, [], '{"status":"pending"}'),
        ]);
        $client = ClientFactory::create($http);
        $result = $client->mobileMoney()->createPayment(new MobileMoneyPaymentRequest(
            10,
            'USD',
            '+243970000003',
            'ORDER-PARTIAL',
            'https://example.com/callback'
        ));

        self::assertFalse($result->accepted);
        self::assertNull($result->providerTransactionId);
        self::assertCount(1, $http->requests);
    }
}
