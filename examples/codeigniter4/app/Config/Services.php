<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseService;
use GuzzleHttp\Client as GuzzleClient;
use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\Config\CheckoutConfiguration;
use Holduix\FlexPay\Checkout\CheckoutManager;
use App\Repositories\CodeIgniterCheckoutStore;
use Holduix\FlexPay\FlexPayClient;
use Nyholm\Psr7\Factory\Psr17Factory;

final class Services extends BaseService
{
    public static function flexPay(bool $getShared = true): FlexPayClient
    {
        if ($getShared) {
            return static::getSharedInstance('flexPay');
        }

        $config = config('FlexPayPackage');
        $factory = new Psr17Factory();

        return new FlexPayClient(
            new FlexPayConfiguration(
                $config->apiBaseUrl,
                $config->cardBaseUrl,
                $config->merchantId,
                $config->apiToken,
                $config->callbackToken
            ),
            new GuzzleClient(['timeout' => 30, 'http_errors' => false]),
            $factory,
            $factory,
            service('logger')
        );
    }

    public static function flexPayCheckout(bool $getShared = true): CheckoutManager
    {
        if ($getShared) {
            return static::getSharedInstance('flexPayCheckout');
        }

        $config = config('FlexPayPackage');
        return new CheckoutManager(
            static::flexPay(),
            new CodeIgniterCheckoutStore(db_connect()),
            service('logger'),
            new CheckoutConfiguration(
                allowedCheckoutHosts: $config->allowedCheckoutHosts,
                pollInitialDelayMilliseconds: (int) env('FLEXPAY_CHECKOUT_POLL_INITIAL_DELAY_MS', 0),
                pollIntervalMilliseconds: (int) env('FLEXPAY_CHECKOUT_POLL_INTERVAL_MS', 4000),
                pollMaximumIntervalMilliseconds: (int) env('FLEXPAY_CHECKOUT_POLL_MAX_INTERVAL_MS', 10000),
                pollTimeoutSeconds: (int) env('FLEXPAY_CHECKOUT_POLL_TIMEOUT_SECONDS', 180),
                statusMinimumRefreshSeconds: (int) env('FLEXPAY_STATUS_MIN_REFRESH_SECONDS', 4)
            )
        );
    }
}
