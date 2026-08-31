<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Tests\Unit;

use Holduix\FlexPay\Checkout\CheckoutState;
use Holduix\FlexPay\Checkout\Exception\InvalidCheckoutTransitionException;
use Holduix\FlexPay\Checkout\Policy\CheckoutStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckoutStateMachineTest extends TestCase
{
    /** @return iterable<string, array{CheckoutState, CheckoutState}> */
    public static function allowedTransitions(): iterable
    {
        yield 'mobile details' => [CheckoutState::CHOOSING_METHOD, CheckoutState::COLLECTING_DETAILS];
        yield 'mobile initializing' => [CheckoutState::COLLECTING_DETAILS, CheckoutState::INITIALIZING];
        yield 'mobile pending' => [CheckoutState::INITIALIZING, CheckoutState::PENDING];
        yield 'verify pending' => [CheckoutState::PENDING, CheckoutState::VERIFYING];
        yield 'verify success' => [CheckoutState::VERIFYING, CheckoutState::SUCCESSFUL];
        yield 'card action' => [CheckoutState::INITIALIZING, CheckoutState::REQUIRES_ACTION];
        yield 'card redirect' => [CheckoutState::REQUIRES_ACTION, CheckoutState::REDIRECTING];
        yield 'return verify' => [CheckoutState::REDIRECTING, CheckoutState::VERIFYING];
        yield 'ambiguous' => [CheckoutState::VERIFYING, CheckoutState::UNKNOWN];
        yield 'resume unknown' => [CheckoutState::UNKNOWN, CheckoutState::VERIFYING];
    }

    #[DataProvider('allowedTransitions')]
    public function testCriticalTransitionIsAllowed(CheckoutState $from, CheckoutState $to): void
    {
        $machine = new CheckoutStateMachine();
        $machine->assertAllowed($from, $to);
        self::assertTrue($machine->canTransition($from, $to));
    }

    public function testSuccessfulCannotBeDowngraded(): void
    {
        $machine = new CheckoutStateMachine();

        self::assertFalse($machine->canTransition(CheckoutState::SUCCESSFUL, CheckoutState::PENDING));
        self::assertFalse($machine->canTransition(CheckoutState::SUCCESSFUL, CheckoutState::FAILED));

        $this->expectException(InvalidCheckoutTransitionException::class);
        $machine->assertAllowed(CheckoutState::SUCCESSFUL, CheckoutState::PENDING);
    }

    public function testInvalidTransitionRaisesExplicitException(): void
    {
        $this->expectException(InvalidCheckoutTransitionException::class);
        (new CheckoutStateMachine())->assertAllowed(CheckoutState::CREATED, CheckoutState::SUCCESSFUL);
    }
}
