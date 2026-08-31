<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout;

use JsonSerializable;

final class CheckoutAction implements JsonSerializable
{
    public function __construct(
        public readonly CheckoutActionType $type,
        public readonly ?string $url = null,
        public readonly ?int $afterMilliseconds = null
    ) {
    }

    public static function none(): self
    {
        return new self(CheckoutActionType::NONE);
    }

    public static function poll(string $url, int $afterMilliseconds): self
    {
        return new self(CheckoutActionType::POLL, $url, $afterMilliseconds);
    }

    public static function redirect(string $url): self
    {
        return new self(CheckoutActionType::REDIRECT, $url);
    }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'url' => $this->url,
            'after_ms' => $this->afterMilliseconds,
        ];
    }

    /** @return array<string, int|string|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
