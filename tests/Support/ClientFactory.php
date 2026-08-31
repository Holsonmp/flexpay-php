<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Support;

use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\FlexPayClient;
use Nyholm\Psr7\Factory\Psr17Factory;

final class ClientFactory
{
    public static function create(FakeHttpClient $httpClient, string $callbackToken = ''): FlexPayClient
    {
        $factory = new Psr17Factory();

        return new FlexPayClient(
            new FlexPayConfiguration(
                apiBaseUrl: 'https://backend.flexpay.cd',
                cardBaseUrl: 'https://cardpayment.flexpay.cd',
                merchantId: 'MERCHANT-TEST',
                apiToken: 'token-test',
                callbackToken: $callbackToken
            ),
            $httpClient,
            $factory,
            $factory
        );
    }
}
