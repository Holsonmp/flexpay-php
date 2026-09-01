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

use Holduix\FlexPay\Enum\PaymentMethod;
use JsonSerializable;

final class CheckoutPublicView implements JsonSerializable
{
    /** @param list<string> $allowedMethods */
    public function __construct(
        public readonly string $checkoutId,
        public readonly string $merchantReference,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $allowedMethods,
        public readonly ?string $selectedMethod,
        public readonly string $status,
        public readonly string $checkoutState,
        public readonly bool $terminal,
        public readonly string $message,
        public readonly ?int $nextPollAfterMilliseconds = null
    ) {
    }

    public static function fromSession(CheckoutSession $session, ?int $nextPollAfterMilliseconds = null): self
    {
        return new self(
            $session->id(),
            $session->merchantReference(),
            $session->amount(),
            $session->currency(),
            array_map(static fn (PaymentMethod $method): string => $method->value, $session->allowedMethods()),
            $session->selectedMethod()?->value,
            $session->normalizedStatus()->value,
            $session->state()->value,
            $session->normalizedStatus()->isFinal(),
            self::messageFor($session),
            $nextPollAfterMilliseconds
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'checkout_id' => $this->checkoutId,
            'merchant_reference' => $this->merchantReference,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'allowed_methods' => $this->allowedMethods,
            'selected_method' => $this->selectedMethod,
            'status' => $this->status,
            'checkout_state' => $this->checkoutState,
            'terminal' => $this->terminal,
            'message' => $this->message,
            'next_poll_after_ms' => $this->nextPollAfterMilliseconds,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function messageFor(CheckoutSession $session): string
    {
        return match ($session->state()) {
            CheckoutState::CREATED, CheckoutState::CHOOSING_METHOD => 'Choisissez un moyen de paiement.',
            CheckoutState::COLLECTING_DETAILS => 'Saisissez les informations demandées.',
            CheckoutState::INITIALIZING => 'Initialisation du paiement en cours.',
            CheckoutState::PENDING => $session->selectedMethod() === PaymentMethod::MOBILE_MONEY
                ? 'Confirmez la demande de paiement sur votre téléphone.'
                : 'Confirmation en attente.',
            CheckoutState::REQUIRES_ACTION, CheckoutState::REDIRECTING =>
                'Redirection vers le portail de paiement sécurisé.',
            CheckoutState::VERIFYING => 'Vérification du paiement en cours.',
            CheckoutState::SUCCESSFUL => 'Paiement confirmé.',
            CheckoutState::FAILED => 'Le paiement n’a pas été confirmé.',
            CheckoutState::CANCELLED => 'Le paiement a été annulé.',
            CheckoutState::EXPIRED => 'La session de paiement a expiré.',
            CheckoutState::UNKNOWN =>
                'La confirmation prend plus de temps que prévu. Votre paiement n’est pas déclaré en échec.',
        };
    }
}
