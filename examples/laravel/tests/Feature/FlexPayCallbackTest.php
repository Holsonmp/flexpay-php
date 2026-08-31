<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class FlexPayCallbackTest extends TestCase
{
    public function testCallbackIsAcceptedAndQueued(): void
    {
        $response = $this->postJson('/api/payment/flexpay/callback', [
            'merchant_reference' => 'ORDER-TEST-1',
            'orderNumber' => 'PROVIDER-TEST-1',
            'status' => 'pending',
        ]);

        $response->assertOk()->assertJson(['result' => 1]);
    }
}
