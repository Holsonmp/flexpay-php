<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Mapper\StatusMapper;
use Holduix\FlexPay\Support\DataExtractor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatusMapperTest extends TestCase
{
    /**
     * @return iterable<string, array{bool|float|int|string, NormalizedPaymentStatus, int|null}>
     */
    public static function statuses(): iterable
    {
        yield 'numeric success' => [0, NormalizedPaymentStatus::SUCCESSFUL, 0];
        yield 'numeric failure' => [1, NormalizedPaymentStatus::FAILED, 1];
        yield 'numeric pending' => [2, NormalizedPaymentStatus::PENDING, 2];
        yield 'success' => ['success', NormalizedPaymentStatus::SUCCESSFUL, 0];
        yield 'verified' => ['verified', NormalizedPaymentStatus::SUCCESSFUL, 0];
        yield 'pending' => ['pending', NormalizedPaymentStatus::PENDING, 2];
        yield 'initiated' => ['initiated', NormalizedPaymentStatus::PENDING, 2];
        yield 'processing' => ['processing', NormalizedPaymentStatus::PROCESSING, 2];
        yield 'requires action' => ['requires_action', NormalizedPaymentStatus::REQUIRES_ACTION, 2];
        yield 'created' => ['created', NormalizedPaymentStatus::CREATED, 2];
        yield 'failed' => ['failed', NormalizedPaymentStatus::FAILED, 1];
        yield 'cancelled' => ['cancelled', NormalizedPaymentStatus::CANCELLED, 1];
        yield 'expired' => ['expired', NormalizedPaymentStatus::EXPIRED, 1];
        yield 'unknown' => ['new_provider_state', NormalizedPaymentStatus::UNKNOWN, null];
        yield 'unconfirmed numeric' => [200, NormalizedPaymentStatus::UNKNOWN, null];
    }

    #[DataProvider('statuses')]
    public function testEveryStatusIsMappedAndKeepsRawValue(
        bool|float|int|string $raw,
        NormalizedPaymentStatus $expected,
        ?int $legacyCode
    ): void {
        $mapper = new StatusMapper(new DataExtractor());
        $mapping = $mapper->map(['status' => $raw]);

        self::assertSame($expected, $mapping->normalizedStatus);
        self::assertSame((string) $raw, $mapping->rawStatus);
        self::assertSame($legacyCode, $mapper->legacyCode($mapping));
    }

    public function testNestedTransactionStatusIsFound(): void
    {
        $mapper = new StatusMapper(new DataExtractor());
        $mapping = $mapper->map(['data' => ['transaction' => ['payment_status' => 'approved']]]);

        self::assertSame(NormalizedPaymentStatus::SUCCESSFUL, $mapping->normalizedStatus);
        self::assertSame('approved', $mapping->rawStatus);
    }
}
