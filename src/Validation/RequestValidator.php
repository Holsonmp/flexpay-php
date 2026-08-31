<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Validation;

use Holduix\FlexPay\DTO\Request\CardPaymentRequest;
use Holduix\FlexPay\DTO\Request\MobileMoneyPaymentRequest;
use Holduix\FlexPay\Exception\ValidationException;
use Holduix\FlexPay\Support\PhoneNumberNormalizer;

final class RequestValidator
{
    public function __construct(
        private readonly PhoneNumberNormalizer $phoneNormalizer,
        private readonly bool $allowInsecureUrls = false
    ) {
    }

    public function validateMobileMoney(MobileMoneyPaymentRequest $request): string
    {
        $this->validateAmount($request->amount);
        $this->validateCurrency($request->currency);
        $this->validateReference($request->merchantReference);
        $this->validateUrl($request->callbackUrl, 'URL de callback');

        return $this->phoneNormalizer->normalize($request->phoneNumber);
    }

    public function validateCard(CardPaymentRequest $request): string
    {
        $this->validateAmount($request->amount);
        $this->validateCurrency($request->currency);
        $this->validateReference($request->merchantReference);
        if (trim($request->description) === '') {
            throw new ValidationException('Description : la valeur est obligatoire.');
        }
        $this->validateUrl($request->callbackUrl, 'URL de callback');
        $this->validateUrl($request->approveUrl, 'URL de succès');
        $this->validateUrl($request->cancelUrl, 'URL d’annulation');
        $this->validateUrl($request->declineUrl, 'URL d’échec');
        $this->validateUrl($request->homeUrl, 'URL de retour');

        return trim($request->customerPhone) === ''
            ? ''
            : $this->phoneNormalizer->normalize($request->customerPhone);
    }

    public function validateProviderTransactionId(string $providerTransactionId): string
    {
        $providerTransactionId = trim($providerTransactionId);
        if ($providerTransactionId === '' || strlen($providerTransactionId) > 128) {
            throw new ValidationException(
                'Identifiant FlexPay : la valeur est obligatoire et limitée à 128 caractères.'
            );
        }

        return $providerTransactionId;
    }

    private function validateAmount(float $amount): void
    {
        if (!is_finite($amount) || $amount <= 0) {
            throw new ValidationException('Montant : la valeur doit être supérieure à zéro.');
        }
        if (abs($amount - round($amount, 2)) > 0.000001) {
            throw new ValidationException('Montant : deux décimales au maximum sont acceptées.');
        }
    }

    private function validateCurrency(string $currency): void
    {
        if (preg_match('/^[A-Za-z]{3}$/', trim($currency)) !== 1) {
            throw new ValidationException(
                'Devise : utilisez un code composé de trois lettres, par exemple CDF ou USD.'
            );
        }
    }

    private function validateReference(string $reference): void
    {
        $length = strlen(trim($reference));
        if ($length < 1 || $length > 128) {
            throw new ValidationException(
                'Référence marchande : la valeur est obligatoire et limitée à 128 caractères.'
            );
        }
    }

    private function validateUrl(string $url, string $label): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ValidationException(sprintf('%s : l’adresse fournie est invalide.', $label));
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'https' && !($this->allowInsecureUrls && $scheme === 'http')) {
            throw new ValidationException(sprintf('%s : l’adresse fournie doit utiliser HTTPS.', $label));
        }
    }
}
