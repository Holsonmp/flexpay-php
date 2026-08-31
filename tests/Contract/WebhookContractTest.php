<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Contract;

use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Exception\InvalidWebhookException;
use Holduix\FlexPay\Tests\Support\ClientFactory;
use Holduix\FlexPay\Tests\Support\FakeHttpClient;
use PHPUnit\Framework\TestCase;

final class WebhookContractTest extends TestCase
{
    public function testJsonCallbackIsParsed(): void
    {
        $body = file_get_contents(__DIR__ . '/../../fixtures/webhooks/success.json');
        self::assertIsString($body);
        $event = ClientFactory::create(new FakeHttpClient([]))->webhooks()->parse([], $body);

        self::assertSame('ORDER-2026-0001', $event->merchantReference);
        self::assertSame('FAKE-SUCCESS-ORDER-2026-0001', $event->providerTransactionId);
        self::assertSame(NormalizedPaymentStatus::SUCCESSFUL, $event->normalizedStatus);
        self::assertSame(50.0, $event->amount);
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
        ClientFactory::create(new FakeHttpClient([]))->webhooks()->parse([], '{"status":"success"}');
    }
}
