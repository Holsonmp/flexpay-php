<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Contracts;

use Holduix\FlexPay\Checkout\CheckoutSession;
use Holduix\FlexPay\Checkout\CheckoutTransition;

interface CheckoutStoreInterface
{
    public function findById(string $checkoutId): ?CheckoutSession;

    public function findByMerchantReference(string $merchantReference): ?CheckoutSession;

    public function findByProviderTransactionId(string $providerTransactionId): ?CheckoutSession;

    public function save(CheckoutSession $session, ?int $expectedVersion = null): void;

    public function recordTransition(CheckoutTransition $transition): void;

    public function hasProcessedCallback(string $eventId): bool;

    public function markCallbackProcessed(string $eventId, string $checkoutId): void;
}
