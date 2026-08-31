<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Security;

use Holduix\FlexPay\Checkout\Exception\CheckoutRedirectException;
use Holduix\FlexPay\Config\CheckoutConfiguration;

final class CheckoutRedirectValidator
{
    public function __construct(private readonly CheckoutConfiguration $configuration)
    {
    }

    public function validate(string $url): string
    {
        $url = trim($url);
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new CheckoutRedirectException('adresse vide ou syntaxiquement invalide');
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'https' && !($scheme === 'http' && $this->configuration->allowInsecureCheckoutUrls)) {
            throw new CheckoutRedirectException('schéma non autorisé');
        }
        if (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null) {
            throw new CheckoutRedirectException('identifiants intégrés dans l’adresse');
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowedHosts = array_map('strtolower', $this->configuration->allowedCheckoutHosts);
        if ($host === '' || !in_array($host, $allowedHosts, true)) {
            throw new CheckoutRedirectException('hôte non autorisé');
        }

        return $url;
    }
}
