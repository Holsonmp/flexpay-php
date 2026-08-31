<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Config;

use Holduix\FlexPay\Exception\ConfigurationException;

final class FlexPayConfiguration
{
    public readonly RetryConfiguration $retry;

    public function __construct(
        public readonly string $apiBaseUrl,
        public readonly string $cardBaseUrl,
        public readonly string $merchantId,
        public readonly string $apiToken,
        public readonly string $callbackToken = '',
        public readonly string $mobileMoneyEndpoint = '/api/rest/v1/paymentService',
        public readonly string $transactionStatusEndpoint = '/api/rest/v1/check',
        public readonly string $cardOrderStatusEndpoint = '/api/rest/v1/vpos/orderNumber',
        public readonly string $cardCheckoutEndpoint = '/v3/pay',
        public readonly string $defaultCurrency = 'CDF',
        public readonly int $timeoutSeconds = 30,
        public readonly bool $allowInsecureUrls = false,
        ?RetryConfiguration $retry = null
    ) {
        $this->retry = $retry ?? new RetryConfiguration();
        $this->validate();
    }

    private function validate(): void
    {
        if (trim($this->merchantId) === '') {
            throw new ConfigurationException('Configuration : le code marchand est obligatoire.');
        }
        if (trim($this->apiToken) === '') {
            throw new ConfigurationException('Configuration : le token API est obligatoire.');
        }
        if ($this->timeoutSeconds < 1 || $this->timeoutSeconds > 300) {
            throw new ConfigurationException('Configuration : le timeout doit être compris entre 1 et 300 secondes.');
        }
        $this->validateBaseUrl($this->apiBaseUrl, 'URL API');
        $this->validateBaseUrl($this->cardBaseUrl, 'URL API carte');

        if (preg_match('/^[A-Z]{3}$/', strtoupper(trim($this->defaultCurrency))) !== 1) {
            throw new ConfigurationException('Configuration : la devise par défaut doit contenir trois lettres.');
        }
    }

    private function validateBaseUrl(string $url, string $label): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ConfigurationException(sprintf('Configuration : %s est invalide.', $label));
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'https' && !($this->allowInsecureUrls && $scheme === 'http')) {
            throw new ConfigurationException(sprintf('Configuration : %s doit utiliser HTTPS.', $label));
        }
    }
}
