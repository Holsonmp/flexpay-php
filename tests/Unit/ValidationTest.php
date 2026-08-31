<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\Exception\ValidationException;
use Holduix\FlexPay\Support\PhoneNumberNormalizer;
use Holduix\FlexPay\Validation\RequestValidator;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testPhoneNormalizationMatchesLegacyDigitsOnlyContract(): void
    {
        self::assertSame('243970000003', (new PhoneNumberNormalizer())->normalize('+243 970-000-003'));
    }

    public function testAmountMustBePositive(): void
    {
        $validator = new RequestValidator(new PhoneNumberNormalizer());
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Montant');

        $validator->validateMobileMoney(new MobileMoneyPaymentRequest(
            0,
            'USD',
            '+243970000003',
            'ORDER-1',
            'https://example.com/callback'
        ));
    }

    public function testCallbackMustUseHttpsByDefault(): void
    {
        $validator = new RequestValidator(new PhoneNumberNormalizer());
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('HTTPS');

        $validator->validateMobileMoney(new MobileMoneyPaymentRequest(
            10,
            'USD',
            '+243970000003',
            'ORDER-1',
            'http://example.com/callback'
        ));
    }
}
