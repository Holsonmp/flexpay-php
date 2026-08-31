<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use DateTimeImmutable;
use Holduix\FlexPay\Checkout\CheckoutAction;
use Holduix\FlexPay\Checkout\CheckoutPublicView;
use Holduix\FlexPay\Checkout\CheckoutSession;
use Holduix\FlexPay\Checkout\CheckoutState;
use Holduix\FlexPay\Checkout\DTO\CheckoutOperationResult;
use Holduix\FlexPay\Checkout\Exception\CheckoutConflictException;
use Holduix\FlexPay\Checkout\Exception\CheckoutRedirectException;
use Holduix\FlexPay\Checkout\Http\CheckoutResponseSerializer;
use Holduix\FlexPay\Checkout\Policy\StatusRefreshPolicy;
use Holduix\FlexPay\Checkout\Security\CheckoutRedirectValidator;
use Holduix\FlexPay\Checkout\Support\InMemoryCheckoutStore;
use Holduix\FlexPay\Config\CheckoutConfiguration;
use Holduix\FlexPay\Enum\PaymentMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckoutSupportTest extends TestCase
{
    public function testSessionRoundTripAndPublicViewFilterSensitiveData(): void
    {
        $session = $this->session();
        $roundTrip = CheckoutSession::fromStorageArray($session->toStorageArray());
        $public = CheckoutPublicView::fromSession($roundTrip, 4000)->toArray();

        self::assertSame($session->id(), $roundTrip->id());
        self::assertSame(['mobile_money', 'card'], $public['allowed_methods']);
        self::assertArrayNotHasKey('provider_transaction_id', $public);
        self::assertArrayNotHasKey('checkout_url', $public);
        self::assertArrayNotHasKey('customer_email', $public);
        self::assertArrayNotHasKey('raw_status', $public);
    }

    public function testInMemoryStoreUsesOptimisticVersionAndUniqueReference(): void
    {
        $store = new InMemoryCheckoutStore();
        $session = $this->session();
        $store->save($session);
        $loaded = $store->findByMerchantReference('ORDER-2026-0001');

        self::assertNotNull($loaded);
        self::assertSame($session->id(), $loaded->id());

        $this->expectException(CheckoutConflictException::class);
        $store->save($session);
    }

    public function testSerializerProducesStablePollAndRedirectActions(): void
    {
        $view = CheckoutPublicView::fromSession($this->session(), 3000);
        $serializer = new CheckoutResponseSerializer();
        $poll = $serializer->operation(new CheckoutOperationResult(
            $view,
            CheckoutAction::poll('/status/chk_test', 3000)
        ));
        $redirect = $serializer->operation(new CheckoutOperationResult(
            $view,
            CheckoutAction::redirect('https://cardpayment.flexpay.cd/v3/pay?token=secret')
        ));

        $pollAction = $poll['action'];
        $redirectAction = $redirect['action'];
        self::assertIsArray($pollAction);
        self::assertIsArray($redirectAction);
        self::assertSame('poll', $pollAction['type']);
        self::assertSame('/status/chk_test', $pollAction['poll_url']);
        self::assertSame(3000, $pollAction['after_ms']);
        self::assertSame('redirect', $redirectAction['type']);
        self::assertArrayHasKey('redirect_url', $redirectAction);
        self::assertArrayNotHasKey('raw_response', $redirect);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidRedirects(): iterable
    {
        yield 'empty' => [''];
        yield 'javascript' => ['javascript:alert(1)'];
        yield 'data' => ['data:text/plain,test'];
        yield 'http' => ['http://cardpayment.flexpay.cd/v3/pay'];
        yield 'wrong host' => ['https://evil.example/v3/pay'];
        yield 'credentials' => ['https://user:pass@cardpayment.flexpay.cd/v3/pay'];
    }

    #[DataProvider('invalidRedirects')]
    public function testRedirectValidatorRefusesUnsafeUrl(string $url): void
    {
        $this->expectException(CheckoutRedirectException::class);
        (new CheckoutRedirectValidator(new CheckoutConfiguration()))->validate($url);
    }

    public function testRedirectValidatorAcceptsObservedHost(): void
    {
        $url = 'https://cardpayment.flexpay.cd/v3/pay?token=redacted';
        self::assertSame(
            $url,
            (new CheckoutRedirectValidator(new CheckoutConfiguration()))->validate($url)
        );
    }

    public function testStatusRefreshPolicyCachesRecentProviderStatusAndBacksOff(): void
    {
        $configuration = new CheckoutConfiguration(
            statusMinimumRefreshSeconds: 4,
            pollIntervalMilliseconds: 4000,
            pollMaximumIntervalMilliseconds: 10000
        );
        $policy = new StatusRefreshPolicy($configuration);
        $session = $this->session();
        $now = new DateTimeImmutable('2026-08-31T12:00:00+00:00');

        self::assertFalse($policy->shouldRefresh($session, $now));
        self::assertSame(0, $policy->nextPollAfterMilliseconds($session, $now));
    }

    private function session(): CheckoutSession
    {
        return CheckoutSession::create(
            'chk_test',
            'ORDER-2026-0001',
            50,
            'USD',
            [PaymentMethod::MOBILE_MONEY, PaymentMethod::CARD],
            'https://example.com/success',
            'https://example.com/cancel',
            'https://example.com/callback',
            'Client Test',
            'client@example.com',
            'Commande test',
            new DateTimeImmutable('2026-08-31T12:00:00+00:00')
        );
    }
}
