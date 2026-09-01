<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use Holduix\FlexPay\Support\Redactor;
use PHPUnit\Framework\TestCase;

final class RedactorTest extends TestCase
{
    public function testSensitiveFieldsAndPhoneAreRedactedRecursively(): void
    {
        $result = (new Redactor())->redact([
            'Authorization' => 'Bearer very-secret',
            'token' => 'very-secret',
            'phone' => '+243970000003',
            'merchant_reference' => 'ORDER-2026-0001',
            'nested' => ['cvv' => '123', 'card_number' => '4111111111111111'],
        ]);

        self::assertSame('[REDACTED]', $result['Authorization']);
        self::assertSame('[REDACTED]', $result['token']);
        self::assertSame('+243******003', $result['phone']);
        self::assertSame('ORDER-2026-0001', $result['merchant_reference']);
        $nested = $result['nested'] ?? null;
        self::assertIsArray($nested);
        self::assertSame('[REDACTED]', $nested['cvv']);
        self::assertSame('[REDACTED]', $nested['card_number']);
    }

    public function testSecretsAreRemovedFromCheckoutUrl(): void
    {
        $result = (new Redactor())->redact([
            'payment_url' => 'https://example.com/pay?authorization=abc&token=def&reference=ORDER-1',
        ]);

        $url = $result['payment_url'] ?? null;
        self::assertIsString($url);
        self::assertStringNotContainsString('abc', $url);
        self::assertStringNotContainsString('def', $url);
        self::assertStringContainsString('ORDER-1', $url);
    }

    public function testPhoneInsideMessageIsMasked(): void
    {
        $result = (new Redactor())->redact(['message' => 'Numéro +243970000003 refusé.']);

        self::assertSame('Numéro +243******003 refusé.', $result['message']);
    }

    public function testSecretsEmbeddedInFreeTextAreRedacted(): void
    {
        $result = (new Redactor())->redact([
            'message' => 'authorization=abc token:very-secret password=hunter2',
        ]);

        self::assertSame(
            'authorization=[REDACTED] token:[REDACTED] password=[REDACTED]',
            $result['message']
        );
    }
}
