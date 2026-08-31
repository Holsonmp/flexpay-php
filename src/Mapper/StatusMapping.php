<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Mapper;

use Holduix\FlexPay\Enum\NormalizedPaymentStatus;

final class StatusMapping
{
    public function __construct(
        public readonly NormalizedPaymentStatus $normalizedStatus,
        public readonly ?string $rawStatus
    ) {
    }
}
