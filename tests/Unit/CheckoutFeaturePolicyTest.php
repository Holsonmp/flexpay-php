<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use Holduix\FlexPay\Checkout\Policy\CheckoutFeaturePolicy;
use Holduix\FlexPay\Exception\ConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckoutFeaturePolicyTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function supportedCombinations(): iterable
    {
        yield 'legacy complet' => ['legacy', 'legacy'];
        yield 'interface package, backend legacy' => ['legacy', 'package'];
        yield 'backend package, interface legacy existante' => ['package', 'legacy'];
        yield 'package complet' => ['package', 'package'];
    }

    #[DataProvider('supportedCombinations')]
    public function testSupportedDevelopmentCombinations(string $backend, string $ui): void
    {
        $policy = new CheckoutFeaturePolicy($backend, $ui);
        self::assertSame($backend, $policy->backendDriver('development'));
        self::assertSame($ui, $policy->uiDriver('development'));
    }

    public function testProductionFallsBackToLegacyByDefault(): void
    {
        $policy = new CheckoutFeaturePolicy('package', 'package');
        self::assertSame('legacy', $policy->backendDriver('production'));
        self::assertSame('legacy', $policy->uiDriver('production'));
        self::assertFalse($policy->isPackageEnabled('production'));
    }

    public function testExplicitProductionPermissionIsRequired(): void
    {
        $policy = new CheckoutFeaturePolicy('package', 'package', true);
        self::assertSame('package', $policy->backendDriver('production'));
        self::assertSame('package', $policy->uiDriver('production'));
    }

    public function testInvalidDriverIsRejected(): void
    {
        $this->expectException(ConfigurationException::class);
        new CheckoutFeaturePolicy('unknown', 'legacy');
    }
}
