<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Contract;

use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Exception\InvalidWebhookException;
use Holduix\FlexPay\Tests\Support\ClientFactory;
use Holduix\FlexPay\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class WebhookContractTest extends TestCase
{
    public function testCallbackAuthenticationIsRequiredByDefault(): void
    {
        $body = file_get_contents(__DIR__ . '/../../fixtures/webhooks/success.json');
        self::assertIsString($body);
        $this->expectException(InvalidWebhookException::class);
        ClientFactory::create(new FakeHttpClient([]))->webhooks()->parse([], $body);
    }

    public function testFormCallbackAndHeaderTokenAreParsed(): void
    {
        $body = file_get_contents(__DIR__ . '/../../fixtures/webhooks/pending-form.txt');
        self::assertIsString($body);
        $event = ClientFactory::create(new FakeHttpClient([]), 'callback-secret')->webhooks()->parse(
            ['X-FlexPay-Callback-Token' => 'callback-secret'],
            $body
        );

        self::assertSame(NormalizedPaymentStatus::PENDING, $event->normalizedStatus);
        self::assertSame('ORDER-2026-0005', $event->merchantReference);
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->expectException(InvalidWebhookException::class);
        ClientFactory::create(new FakeHttpClient([]), 'expected')->webhooks()->parse(
            ['X-FlexPay-Callback-Token' => 'wrong'],
            'merchant_reference=ORDER-1&status=pending'
        );
    }

    public function testCallbackWithoutAnyReferenceIsRejected(): void
    {
        $this->expectException(InvalidWebhookException::class);
        ClientFactory::create(new FakeHttpClient([]), 'callback-secret')->webhooks()->parse(
            ['X-FlexPay-Callback-Token' => 'callback-secret'],
            '{"status":"success"}'
        );
    }

    public function testAuthenticatedJsonCallbackIsParsed(): void
    {
        $event = ClientFactory::create(new FakeHttpClient([]), 'callback-secret')->webhooks()->parse(
            ['X-FlexPay-Callback-Token' => 'callback-secret'],
            '{"merchant_reference":"ORDER-JSON","orderNumber":"PROVIDER-JSON","status":"pending"}'
        );

        self::assertSame('ORDER-JSON', $event->merchantReference);
        self::assertSame('PROVIDER-JSON', $event->providerTransactionId);
    }

    public function testTruncatedJsonIsRejectedWithoutFormFallback(): void
    {
        $this->expectException(InvalidWebhookException::class);
        ClientFactory::create(new FakeHttpClient([]), 'callback-secret')->webhooks()->parse(
            ['X-FlexPay-Callback-Token' => 'callback-secret'],
            '{"merchant_reference":"ORDER-JSON"'
        );
    }

    public function testDuplicateTokenHeaderIsRejected(): void
    {
        $this->expectException(InvalidWebhookException::class);
        ClientFactory::create(new FakeHttpClient([]), 'callback-secret')->webhooks()->parse(
            ['X-FlexPay-Callback-Token' => ['callback-secret', 'callback-secret']],
            'merchant_reference=ORDER-1&status=pending'
        );
    }

    public function testTokenInQueryIsRejectedByDefault(): void
    {
        $this->expectException(InvalidWebhookException::class);
        ClientFactory::create(new FakeHttpClient([]), 'callback-secret')->webhooks()->parse(
            [],
            'merchant_reference=ORDER-1&status=pending',
            ['token' => 'callback-secret']
        );
    }

    public function testOversizedCallbackIsRejectedBeforeParsing(): void
    {
        $configuration = new FlexPayConfiguration(
            apiBaseUrl: 'https://backend.flexpay.cd',
            cardBaseUrl: 'https://cardpayment.flexpay.cd',
            merchantId: 'MERCHANT-TEST',
            apiToken: 'token-test',
            callbackToken: 'callback-secret',
            maxCallbackBodyBytes: 1024
        );

        $this->expectException(InvalidWebhookException::class);
        ClientFactory::create(
            new FakeHttpClient([]),
            configuration: $configuration
        )->webhooks()->parse(
            ['X-FlexPay-Callback-Token' => 'callback-secret'],
            str_repeat('a', 1025)
        );
    }
}
