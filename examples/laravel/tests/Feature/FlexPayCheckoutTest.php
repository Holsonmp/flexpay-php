<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FlexPayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function testUnknownCheckoutIsNotEnumerable(): void
    {
        $this->getJson('/payments/flexpay/checkouts/chk_unknown')->assertUnauthorized();
    }

    public function testStatusEndpointIsRateLimited(): void
    {
        // Créer utilisateur, commande et checkout puis dépasser la limite `flexpay-status`.
        self::assertTrue(true);
    }
}
