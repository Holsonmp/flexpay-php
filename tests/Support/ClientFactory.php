<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Support;

use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\Contracts\HostedCardCheckoutInterface;
use Holduix\FlexPay\FlexPayClient;
use Nyholm\Psr7\Factory\Psr17Factory;

final class ClientFactory
{
    public static function create(
        FakeHttpClient $httpClient,
        string $callbackToken = 'callback-test',
        ?HostedCardCheckoutInterface $hostedCardCheckout = null,
        bool $withFakeHostedCheckout = true,
        ?FlexPayConfiguration $configuration = null
    ): FlexPayClient {
        $factory = new Psr17Factory();

        return new FlexPayClient(
            $configuration ?? new FlexPayConfiguration(
                apiBaseUrl: 'https://backend.flexpay.cd',
                cardBaseUrl: 'https://cardpayment.flexpay.cd',
                merchantId: 'MERCHANT-TEST',
                apiToken: 'token-test',
                callbackToken: $callbackToken
            ),
            $httpClient,
            $factory,
            $factory,
            hostedCardCheckout: $hostedCardCheckout
                ?? ($withFakeHostedCheckout ? new FakeHostedCardCheckout() : null)
        );
    }
}
