<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\Config\RetryConfiguration;
use Holduix\FlexPay\Exception\ConfigurationException;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    public function testValidConfigurationKeepsConfirmedEndpoints(): void
    {
        $configuration = new FlexPayConfiguration(
            apiBaseUrl: 'https://backend.flexpay.cd',
            cardBaseUrl: 'https://cardpayment.flexpay.cd',
            merchantId: 'M1',
            apiToken: 'secret'
        );

        self::assertSame('/api/rest/v1/paymentService', $configuration->mobileMoneyEndpoint);
        self::assertSame('/api/rest/v1/check', $configuration->transactionStatusEndpoint);
        self::assertSame('/api/rest/v1/vpos/orderNumber', $configuration->cardOrderStatusEndpoint);
        self::assertSame('/v3/pay', $configuration->cardCheckoutEndpoint);
        self::assertSame(1, $configuration->retry->maxAttempts);
    }

    public function testMissingTokenIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        new FlexPayConfiguration('https://example.com', 'https://example.com', 'M1', '');
    }

    public function testHttpRequiresExplicitDevelopmentOptIn(): void
    {
        $this->expectException(ConfigurationException::class);
        new FlexPayConfiguration('http://localhost', 'https://example.com', 'M1', 'token');
    }

    public function testRetryCannotExceedSafetyBoundary(): void
    {
        $this->expectException(ConfigurationException::class);
        new RetryConfiguration(maxAttempts: 6);
    }
}
