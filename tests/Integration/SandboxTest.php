<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Integration;

use GuzzleHttp\Client;
use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\FlexPayClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class SandboxTest extends TestCase
{
    public function testSandboxMobileMoneyOnlyWhenExplicitlyEnabled(): void
    {
        if (filter_var((string) getenv('FLEXPAY_SANDBOX_ENABLED'), FILTER_VALIDATE_BOOLEAN) !== true) {
            self::markTestSkipped('Sandbox FlexPay désactivé par défaut.');
        }

        $required = [
            'FLEXPAY_BASE_URL',
            'FLEXPAY_MERCHANT_ID',
            'FLEXPAY_API_TOKEN',
            'FLEXPAY_CALLBACK_URL',
            'FLEXPAY_TEST_PHONE',
            'FLEXPAY_TEST_AMOUNT',
            'FLEXPAY_TEST_CURRENCY',
        ];
        foreach ($required as $name) {
            self::assertNotSame('', trim((string) getenv($name)), sprintf('%s est obligatoire.', $name));
        }

        $factory = new Psr17Factory();
        $client = new FlexPayClient(
            new FlexPayConfiguration(
                apiBaseUrl: (string) getenv('FLEXPAY_BASE_URL'),
                cardBaseUrl: (string) getenv('FLEXPAY_BASE_URL'),
                merchantId: (string) getenv('FLEXPAY_MERCHANT_ID'),
                apiToken: (string) getenv('FLEXPAY_API_TOKEN')
            ),
            new Client(['timeout' => 30, 'http_errors' => false]),
            $factory,
            $factory
        );

        $result = $client->mobileMoney()->createPayment(new MobileMoneyPaymentRequest(
            amount: (float) getenv('FLEXPAY_TEST_AMOUNT'),
            currency: (string) getenv('FLEXPAY_TEST_CURRENCY'),
            phoneNumber: (string) getenv('FLEXPAY_TEST_PHONE'),
            merchantReference: 'SANDBOX-' . gmdate('YmdHis'),
            callbackUrl: (string) getenv('FLEXPAY_CALLBACK_URL')
        ));

        self::assertNotSame('', $result->transactionReference);
    }
}
