<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

final class ConfirmFlexPayPayment implements ShouldQueue
{
    /** @param array<string, mixed> $event */
    public function __construct(public readonly array $event)
    {
    }

    public function handle(): void
    {
        // Verrouiller le paiement, relire FlexPay, comparer les valeurs et confirmer une seule fois.
    }
}
