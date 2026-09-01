<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout;

use DateTimeImmutable;
use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Enum\PaymentMethod;

final class CheckoutSession
{
    /**
     * @param list<PaymentMethod> $allowedMethods
     */
    public function __construct(
        private string $id,
        private string $merchantReference,
        private float $amount,
        private string $currency,
        private array $allowedMethods,
        private string $successUrl,
        private string $cancelUrl,
        private string $callbackUrl,
        private string $customerName,
        private string $customerEmail,
        private string $description,
        private CheckoutState $state,
        private NormalizedPaymentStatus $normalizedStatus,
        private ?PaymentMethod $selectedMethod,
        private ?string $rawStatus,
        private ?string $providerTransactionId,
        private ?string $providerTransactionCandidate,
        private ?string $checkoutUrl,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $expiresAt,
        private ?DateTimeImmutable $callbackProcessedAt,
        private ?DateTimeImmutable $lastProviderRefreshAt,
        private ?DateTimeImmutable $lastProviderOccurredAt,
        private int $consecutiveNetworkErrors,
        private int $version
    ) {
    }

    /** @param list<PaymentMethod> $allowedMethods */
    public static function create(
        string $id,
        string $merchantReference,
        float $amount,
        string $currency,
        array $allowedMethods,
        string $successUrl,
        string $cancelUrl,
        string $callbackUrl,
        string $customerName,
        string $customerEmail,
        string $description,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $expiresAt = null
    ): self {
        return new self(
            $id,
            $merchantReference,
            $amount,
            strtoupper($currency),
            $allowedMethods,
            $successUrl,
            $cancelUrl,
            $callbackUrl,
            $customerName,
            $customerEmail,
            $description,
            CheckoutState::CREATED,
            NormalizedPaymentStatus::CREATED,
            null,
            null,
            null,
            null,
            null,
            $createdAt,
            $createdAt,
            $expiresAt,
            null,
            null,
            null,
            0,
            1
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function merchantReference(): string
    {
        return $this->merchantReference;
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /** @return list<PaymentMethod> */
    public function allowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function selectedMethod(): ?PaymentMethod
    {
        return $this->selectedMethod;
    }

    public function state(): CheckoutState
    {
        return $this->state;
    }

    public function normalizedStatus(): NormalizedPaymentStatus
    {
        return $this->normalizedStatus;
    }

    public function rawStatus(): ?string
    {
        return $this->rawStatus;
    }

    public function providerTransactionId(): ?string
    {
        return $this->providerTransactionId;
    }

    public function providerTransactionCandidate(): ?string
    {
        return $this->providerTransactionCandidate;
    }

    public function checkoutUrl(): ?string
    {
        return $this->checkoutUrl;
    }

    public function successUrl(): string
    {
        return $this->successUrl;
    }

    public function cancelUrl(): string
    {
        return $this->cancelUrl;
    }

    public function callbackUrl(): string
    {
        return $this->callbackUrl;
    }

    public function customerName(): string
    {
        return $this->customerName;
    }

    public function customerEmail(): string
    {
        return $this->customerEmail;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function expiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function callbackProcessedAt(): ?DateTimeImmutable
    {
        return $this->callbackProcessedAt;
    }

    public function lastProviderRefreshAt(): ?DateTimeImmutable
    {
        return $this->lastProviderRefreshAt;
    }

    public function lastProviderOccurredAt(): ?DateTimeImmutable
    {
        return $this->lastProviderOccurredAt;
    }

    public function consecutiveNetworkErrors(): int
    {
        return $this->consecutiveNetworkErrors;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function selectMethod(PaymentMethod $method, DateTimeImmutable $now): void
    {
        $this->selectedMethod = $method;
        $this->updatedAt = $now;
    }

    public function setProviderObservation(
        NormalizedPaymentStatus $status,
        ?string $rawStatus,
        ?string $providerTransactionId,
        ?string $checkoutUrl,
        DateTimeImmutable $now,
        ?DateTimeImmutable $providerOccurredAt = null,
        bool $providerWasContacted = true
    ): void {
        $this->normalizedStatus = $status;
        $this->rawStatus = $rawStatus;
        // Une URL de redirection est une donnée transitoire : elle ne devient jamais un état persistant.
        if ($providerWasContacted) {
            $this->lastProviderRefreshAt = $now;
        }
        $this->lastProviderOccurredAt = $providerOccurredAt ?? $this->lastProviderOccurredAt;
        $this->consecutiveNetworkErrors = 0;
        $this->updatedAt = $now;
    }

    public function applyTransition(CheckoutState $to, DateTimeImmutable $now): CheckoutTransition
    {
        $from = $this->state;
        $this->state = $to;
        $this->updatedAt = $now;
        ++$this->version;

        return new CheckoutTransition($this->id, $from, $to, $now, 'checkout', $this->version);
    }

    public function registerNetworkError(DateTimeImmutable $now): void
    {
        ++$this->consecutiveNetworkErrors;
        $this->lastProviderRefreshAt = $now;
        $this->updatedAt = $now;
        ++$this->version;
    }

    public function markProviderRefreshStarted(DateTimeImmutable $now): void
    {
        $this->lastProviderRefreshAt = $now;
        $this->updatedAt = $now;
        ++$this->version;
    }

    public function attachProviderTransactionId(string $providerTransactionId, DateTimeImmutable $now): void
    {
        $this->providerTransactionId = trim($providerTransactionId);
        $this->providerTransactionCandidate = null;
        $this->updatedAt = $now;
        ++$this->version;
    }

    public function rememberProviderTransactionCandidate(string $providerTransactionId, DateTimeImmutable $now): void
    {
        $providerTransactionId = trim($providerTransactionId);
        if ($providerTransactionId === '' || $providerTransactionId === $this->providerTransactionCandidate) {
            return;
        }

        $this->providerTransactionCandidate = $providerTransactionId;
        $this->updatedAt = $now;
        ++$this->version;
    }

    public function markCallbackProcessed(DateTimeImmutable $now): void
    {
        $this->callbackProcessedAt = $now;
        $this->updatedAt = $now;
        ++$this->version;
    }

    /** @return array<string, mixed> */
    public function toStorageArray(): array
    {
        return [
            'checkout_id' => $this->id,
            'merchant_reference' => $this->merchantReference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'allowed_methods' => array_map(
                static fn (PaymentMethod $method): string => $method->value,
                $this->allowedMethods
            ),
            'selected_method' => $this->selectedMethod?->value,
            'checkout_state' => $this->state->value,
            'normalized_status' => $this->normalizedStatus->value,
            'raw_status' => $this->rawStatus,
            'provider_transaction_id' => $this->providerTransactionId,
            'provider_transaction_candidate' => $this->providerTransactionCandidate,
            'success_url' => $this->successUrl,
            'cancel_url' => $this->cancelUrl,
            'callback_url' => $this->callbackUrl,
            'customer_name' => $this->customerName,
            'customer_email' => $this->customerEmail,
            'description' => $this->description,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'expires_at' => $this->expiresAt?->format(DATE_ATOM),
            'callback_processed_at' => $this->callbackProcessedAt?->format(DATE_ATOM),
            'last_provider_refresh_at' => $this->lastProviderRefreshAt?->format(DATE_ATOM),
            'last_provider_occurred_at' => $this->lastProviderOccurredAt?->format(DATE_ATOM),
            'consecutive_network_errors' => $this->consecutiveNetworkErrors,
            'version' => $this->version,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromStorageArray(array $data): self
    {
        $methods = [];
        foreach ((array) ($data['allowed_methods'] ?? []) as $method) {
            $methods[] = PaymentMethod::from(self::stringValue($method, 'allowed_methods'));
        }
        $selected = $data['selected_method'] ?? null;

        return new self(
            self::stringValue($data['checkout_id'] ?? null, 'checkout_id'),
            self::stringValue($data['merchant_reference'] ?? null, 'merchant_reference'),
            self::floatValue($data['amount'] ?? null, 'amount'),
            self::stringValue($data['currency'] ?? null, 'currency'),
            $methods,
            self::stringValue($data['success_url'] ?? null, 'success_url'),
            self::stringValue($data['cancel_url'] ?? null, 'cancel_url'),
            self::stringValue($data['callback_url'] ?? null, 'callback_url'),
            self::stringValue($data['customer_name'] ?? '', 'customer_name'),
            self::stringValue($data['customer_email'] ?? '', 'customer_email'),
            self::stringValue($data['description'] ?? '', 'description'),
            CheckoutState::from(self::stringValue($data['checkout_state'] ?? null, 'checkout_state')),
            NormalizedPaymentStatus::from(
                self::stringValue($data['normalized_status'] ?? null, 'normalized_status')
            ),
            is_string($selected) && $selected !== '' ? PaymentMethod::from($selected) : null,
            self::nullableString($data['raw_status'] ?? null),
            self::nullableString($data['provider_transaction_id'] ?? null),
            self::nullableString($data['provider_transaction_candidate'] ?? null),
            null,
            new DateTimeImmutable(self::stringValue($data['created_at'] ?? null, 'created_at')),
            new DateTimeImmutable(self::stringValue($data['updated_at'] ?? null, 'updated_at')),
            self::nullableDate($data['expires_at'] ?? null),
            self::nullableDate($data['callback_processed_at'] ?? null),
            self::nullableDate($data['last_provider_refresh_at'] ?? null),
            self::nullableDate($data['last_provider_occurred_at'] ?? null),
            self::intValue($data['consecutive_network_errors'] ?? 0, 'consecutive_network_errors'),
            self::intValue($data['version'] ?? null, 'version')
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function nullableDate(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && $value !== '' ? new DateTimeImmutable($value) : null;
    }

    private static function stringValue(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Session checkout : champ texte invalide : ' . $field);
        }

        return $value;
    }

    private static function floatValue(mixed $value, string $field): float
    {
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            throw new \InvalidArgumentException('Session checkout : montant invalide : ' . $field);
        }

        return floatval($value);
    }

    private static function intValue(mixed $value, string $field): int
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException('Session checkout : entier invalide : ' . $field);
        }

        return intval($value);
    }
}
