<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout;

use DateTimeImmutable;
use JsonSerializable;

final class CheckoutTransition implements JsonSerializable
{
    public function __construct(
        public readonly string $checkoutId,
        public readonly CheckoutState $from,
        public readonly CheckoutState $to,
        public readonly DateTimeImmutable $occurredAt,
        public readonly string $source,
        public readonly int $version
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'checkout_id' => $this->checkoutId,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'source' => $this->source,
            'version' => $this->version,
        ];
    }

    /** @return array<string, int|string> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
