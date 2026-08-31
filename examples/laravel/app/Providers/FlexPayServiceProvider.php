<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\DispatchCheckoutEvents;
use App\Repositories\EloquentCheckoutStore;
use GuzzleHttp\Client as GuzzleClient;
use Holduix\FlexPay\Checkout\CheckoutManager;
use Holduix\FlexPay\Checkout\Contracts\CheckoutEventListenerInterface;
use Holduix\FlexPay\Checkout\Contracts\CheckoutStoreInterface;
use Holduix\FlexPay\Config\CheckoutConfiguration;
use Holduix\FlexPay\Config\FlexPayConfiguration;
use Holduix\FlexPay\FlexPayClient;
use Illuminate\Support\ServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;

final class FlexPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FlexPayClient::class, function (): FlexPayClient {
            $factory = new Psr17Factory();
            return new FlexPayClient(
                new FlexPayConfiguration(
                    config('flexpay.api_base_url'),
                    config('flexpay.card_base_url'),
                    config('flexpay.merchant_id'),
                    config('flexpay.api_token'),
                    config('flexpay.callback_token')
                ),
                new GuzzleClient(['timeout' => 30, 'http_errors' => false]),
                $factory,
                $factory,
                app('log')
            );
        });

        $this->app->singleton(CheckoutStoreInterface::class, EloquentCheckoutStore::class);
        $this->app->singleton(CheckoutEventListenerInterface::class, DispatchCheckoutEvents::class);
        $this->app->singleton(CheckoutManager::class, function (): CheckoutManager {
            return new CheckoutManager(
                $this->app->make(FlexPayClient::class),
                $this->app->make(CheckoutStoreInterface::class),
                $this->app->make('log'),
                new CheckoutConfiguration(
                    allowedCheckoutHosts: config('flexpay.allowed_checkout_hosts'),
                    pollInitialDelayMilliseconds: config('flexpay.poll_initial_delay_ms'),
                    pollIntervalMilliseconds: config('flexpay.poll_interval_ms'),
                    pollMaximumIntervalMilliseconds: config('flexpay.poll_max_interval_ms'),
                    pollTimeoutSeconds: config('flexpay.poll_timeout_seconds'),
                    statusMinimumRefreshSeconds: config('flexpay.status_min_refresh_seconds')
                ),
                $this->app->make(CheckoutEventListenerInterface::class)
            );
        });
    }
}
