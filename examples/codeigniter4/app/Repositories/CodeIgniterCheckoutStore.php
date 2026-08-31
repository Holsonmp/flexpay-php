<?php

declare(strict_types=1);

namespace App\Repositories;

use CodeIgniter\Database\BaseConnection;
use Holduix\FlexPay\Checkout\CheckoutSession;
use Holduix\FlexPay\Checkout\CheckoutTransition;
use Holduix\FlexPay\Checkout\Contracts\CheckoutStoreInterface;
use Holduix\FlexPay\Checkout\Exception\CheckoutConflictException;

final class CodeIgniterCheckoutStore implements CheckoutStoreInterface
{
    public function __construct(private readonly BaseConnection $database)
    {
    }

    public function findById(string $checkoutId): ?CheckoutSession
    {
        return $this->findOne('checkout_id', $checkoutId);
    }

    public function findByMerchantReference(string $merchantReference): ?CheckoutSession
    {
        return $this->findOne('merchant_reference', $merchantReference);
    }

    public function findByProviderTransactionId(string $providerTransactionId): ?CheckoutSession
    {
        return $this->findOne('provider_transaction_id', $providerTransactionId);
    }

    public function save(CheckoutSession $session, ?int $expectedVersion = null): void
    {
        $payload = $session->toStorageArray();
        $row = [
            'checkout_id' => $session->id(),
            'merchant_reference' => $session->merchantReference(),
            'provider_transaction_id' => $session->providerTransactionId(),
            'status' => $session->normalizedStatus()->value,
            'version' => $session->version(),
            // En production, chiffrer le payload car l’URL hébergée peut contenir un token.
            'payload_json' => json_encode($payload, JSON_THROW_ON_ERROR),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($expectedVersion === null) {
            $row['created_at'] = date('Y-m-d H:i:s');
            if (!$this->database->table('flexpay_checkout_sessions')->insert($row)) {
                throw new CheckoutConflictException('Insertion concurrente du checkout.', 'duplicate_checkout');
            }
            return;
        }

        $this->database->table('flexpay_checkout_sessions')
            ->where('checkout_id', $session->id())
            ->where('version', $expectedVersion)
            ->update($row);
        if ($this->database->affectedRows() !== 1) {
            throw new CheckoutConflictException('Version checkout concurrente.', 'concurrent_update');
        }
    }

    public function recordTransition(CheckoutTransition $transition): void
    {
        // La contrainte unique (checkout_id, version) protège également les effets rejoués.
        $this->database->table('flexpay_checkout_transitions')->insert($transition->toArray());
    }

    public function hasProcessedCallback(string $eventId): bool
    {
        return $this->database->table('flexpay_checkout_callbacks')
            ->where('event_id', $eventId)
            ->countAllResults() > 0;
    }

    public function markCallbackProcessed(string $eventId, string $checkoutId): void
    {
        $this->database->table('flexpay_checkout_callbacks')->ignore(true)->insert([
            'event_id' => $eventId,
            'checkout_id' => $checkoutId,
            'processed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function findOne(string $column, string $value): ?CheckoutSession
    {
        $row = $this->database->table('flexpay_checkout_sessions')
            ->where($column, $value)
            ->get()
            ->getRowArray();
        if ($row === null) {
            return null;
        }
        $payload = json_decode((string) $row['payload_json'], true, 512, JSON_THROW_ON_ERROR);

        return CheckoutSession::fromStorageArray($payload);
    }
}
