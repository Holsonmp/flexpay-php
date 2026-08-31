<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Contract;

use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;
use Holduix\FlexPay\Tests\Support\ClientFactory;
use Holduix\FlexPay\Tests\Support\FakeHttpClient;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class TransactionContractTest extends TestCase
{
    public function testMobileStatusUsesConfirmedEndpoint(): void
    {
        $body = file_get_contents(__DIR__ . '/../../fixtures/mobile-money/payment-success.json');
        self::assertIsString($body);
        $http = new FakeHttpClient([new Response(200, [], $body)]);
        $result = ClientFactory::create($http)->transactions()->getStatus('FAKE-SUCCESS-ORDER-2026-0001');

        self::assertSame(NormalizedPaymentStatus::SUCCESSFUL, $result->normalizedStatus);
        self::assertSame(PaymentMethod::MOBILE_MONEY, $result->paymentMethod);
        self::assertSame(
            'https://backend.flexpay.cd/api/rest/v1/check/FAKE-SUCCESS-ORDER-2026-0001',
            (string) $http->requests[0]->getUri()
        );
    }

    public function testCardStatusUsesConfirmedOrderNumberEndpoint(): void
    {
        $http = new FakeHttpClient([new Response(200, [], '{"orderNumber":"CARD-1","status":"pending"}')]);
        $result = ClientFactory::create($http)->transactions()->getCardOrderStatus('CARD-1');

        self::assertSame(NormalizedPaymentStatus::PENDING, $result->normalizedStatus);
        self::assertSame(PaymentMethod::CARD, $result->paymentMethod);
        self::assertSame(
            'https://cardpayment.flexpay.cd/api/rest/v1/vpos/orderNumber/CARD-1',
            (string) $http->requests[0]->getUri()
        );
    }
}
