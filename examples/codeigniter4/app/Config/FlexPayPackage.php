<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class FlexPayPackage extends BaseConfig
{
    public string $apiBaseUrl = 'https://backend.flexpay.cd';
    public string $cardBaseUrl = 'https://cardpayment.flexpay.cd';
    public string $merchantId = '';
    public string $apiToken = '';
    public string $callbackToken = '';
    public string $driver = 'legacy';
    public string $checkoutUi = 'legacy';
    public bool $allowPackageInProduction = false;
    /** @var list<string> */
    public array $allowedCheckoutHosts = ['cardpayment.flexpay.cd'];

    public function __construct()
    {
        parent::__construct();
        $this->merchantId = (string) env('FLEXPAY_MERCHANT', '');
        $this->apiToken = (string) env('FLEXPAY_TOKEN', '');
        $this->callbackToken = (string) env('FLEXPAY_CALLBACK_TOKEN', '');
        $this->driver = strtolower((string) env('FLEXPAY_DRIVER', 'legacy'));
        $this->checkoutUi = strtolower((string) env('FLEXPAY_CHECKOUT_UI', 'legacy'));
        $this->allowPackageInProduction = filter_var(
            (string) env('FLEXPAY_ALLOW_PACKAGE_IN_PRODUCTION', 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
        $this->allowedCheckoutHosts = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FLEXPAY_CARD_ALLOWED_CHECKOUT_HOSTS', 'cardpayment.flexpay.cd'))
        )));
    }
}
