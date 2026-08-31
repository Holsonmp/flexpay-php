<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use Holduix\FlexPay\DTO\Response\TransactionStatusResult;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class DtoSerializationTest extends TestCase
{
    public function testResultSerializesTypedAndRawStatuses(): void
    {
        $result = new TransactionStatusResult(
            NormalizedPaymentStatus::UNKNOWN,
            'new_state',
            ['status' => 'new_state'],
            'ORDER-1',
            'PROVIDER-1',
            PaymentMethod::CARD,
            10,
            'USD'
        );
        $array = $result->toArray();

        self::assertSame('unknown', $array['normalizedStatus']);
        self::assertSame('new_state', $array['rawStatus']);
        self::assertSame(['status' => 'new_state'], $array['rawResponse']);
        self::assertJson(json_encode($result, JSON_THROW_ON_ERROR));
    }
}
