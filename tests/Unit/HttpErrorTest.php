<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\Exception\AuthenticationException;
use Holduix\FlexPay\Exception\InvalidResponseException;
use Holduix\FlexPay\Exception\TimeoutException;
use Holduix\FlexPay\Tests\Support\ClientFactory;
use Holduix\FlexPay\Tests\Support\FakeHttpClient;
use Holduix\FlexPay\Tests\Support\TestNetworkException;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class HttpErrorTest extends TestCase
{
    public function testAuthenticationErrorIsTypedAndSanitized(): void
    {
        $http = new FakeHttpClient([new Response(401, [], '{"code":"AUTH","message":"Refusé","token":"leak"}')]);
        try {
            ClientFactory::create($http)->transactions()->getStatus('ORDER-1');
            self::fail('Une AuthenticationException était attendue.');
        } catch (AuthenticationException $exception) {
            self::assertSame(401, $exception->httpStatus);
            self::assertSame('AUTH', $exception->providerCode);
            self::assertSame('[REDACTED]', $exception->rawResponse['token']);
        }
    }

    public function testInvalidJsonIsRejected(): void
    {
        try {
            ClientFactory::create(new FakeHttpClient([
                new Response(200, [], '<html>Bearer secret-token token=leak +243970000003</html>'),
            ]))->transactions()->getStatus('ORDER-1');
            self::fail('Une InvalidResponseException était attendue.');
        } catch (InvalidResponseException $exception) {
            self::assertArrayNotHasKey('body', $exception->rawResponse);
        }
    }

    public function testEmptyBodyIsRejected(): void
    {
        $this->expectException(InvalidResponseException::class);
        ClientFactory::create(new FakeHttpClient([new Response(200, [], '')]))
            ->transactions()
            ->getStatus('ORDER-1');
    }

    public function testCreationTimeoutIsMarkedAmbiguousAndNotRetried(): void
    {
        $exception = new TestNetworkException('Connection timed out', new Request('POST', 'https://example.com'));
        $http = new FakeHttpClient([$exception]);

        try {
            ClientFactory::create($http)->mobileMoney()->createPayment(new MobileMoneyPaymentRequest(
                10,
                'USD',
                '+243970000003',
                'ORDER-AMBIGUOUS',
                'https://example.com/callback'
            ));
            self::fail('Une TimeoutException était attendue.');
        } catch (TimeoutException $timeout) {
            self::assertTrue($timeout->ambiguous);
            self::assertFalse($timeout->retryable);
            self::assertCount(1, $http->requests);
        }
    }

    public function testOversizedProviderResponseIsRejected(): void
    {
        $configuration = new FlexPayConfiguration(
            apiBaseUrl: 'https://backend.flexpay.cd',
            cardBaseUrl: 'https://cardpayment.flexpay.cd',
            merchantId: 'MERCHANT-TEST',
            apiToken: 'token-test',
            callbackToken: 'callback-test',
            maxProviderResponseBytes: 1024
        );

        $this->expectException(InvalidResponseException::class);
        ClientFactory::create(
            new FakeHttpClient([new Response(200, [], str_repeat('a', 1025))]),
            configuration: $configuration
        )->transactions()->getStatus('ORDER-1');
    }
}
