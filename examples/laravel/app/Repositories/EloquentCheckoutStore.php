<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace App\Repositories;

use App\Models\FlexPayCheckout;
use Holduix\FlexPay\Checkout\CheckoutSession;
use Holduix\FlexPay\Checkout\CheckoutTransition;
use Holduix\FlexPay\Checkout\Contracts\CheckoutStoreInterface;
use Holduix\FlexPay\Checkout\Exception\CheckoutConflictException;
use Illuminate\Support\Facades\DB;

final class EloquentCheckoutStore implements CheckoutStoreInterface
{
    public function findById(string $checkoutId): ?CheckoutSession
    {
        return $this->hydrate(FlexPayCheckout::query()->where('checkout_id', $checkoutId)->first());
    }

    public function findByMerchantReference(string $merchantReference): ?CheckoutSession
    {
        return $this->hydrate(FlexPayCheckout::query()->where('merchant_reference', $merchantReference)->first());
    }

    public function findByProviderTransactionId(string $providerTransactionId): ?CheckoutSession
    {
        return $this->hydrate(
            FlexPayCheckout::query()->where('provider_transaction_id', $providerTransactionId)->first()
        );
    }

    public function save(CheckoutSession $session, ?int $expectedVersion = null): void
    {
        $values = [
            'merchant_reference' => $session->merchantReference(),
            'provider_transaction_id' => $session->providerTransactionId(),
            'status' => $session->normalizedStatus()->value,
            'version' => $session->version(),
            // Appliquer la politique de chiffrement de l’application aux données client persistées.
            'payload_json' => json_encode($session->toStorageArray(), JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ];
        if ($expectedVersion === null) {
            FlexPayCheckout::query()->create(['checkout_id' => $session->id()] + $values);
            return;
        }
        $updated = FlexPayCheckout::query()
            ->where('checkout_id', $session->id())
            ->where('version', $expectedVersion)
            ->update($values);
        if ($updated !== 1) {
            throw new CheckoutConflictException('Version checkout concurrente.', 'concurrent_update');
        }
    }

    public function recordTransition(CheckoutTransition $transition): void
    {
        DB::table('flexpay_checkout_transitions')->insert($transition->toArray());
    }

    public function hasProcessedCallback(string $eventId): bool
    {
        return DB::table('flexpay_checkout_callbacks')->where('event_id', $eventId)->exists();
    }

    public function markCallbackProcessed(string $eventId, string $checkoutId): void
    {
        DB::table('flexpay_checkout_callbacks')->insertOrIgnore([
            'event_id' => $eventId,
            'checkout_id' => $checkoutId,
            'processed_at' => now(),
        ]);
    }

    private function hydrate(?FlexPayCheckout $model): ?CheckoutSession
    {
        if ($model === null) {
            return null;
        }
        return CheckoutSession::fromStorageArray(json_decode(
            (string) $model->payload_json,
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
    }
}
