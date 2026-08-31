<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Holduix\FlexPay\Checkout\CheckoutManager;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutCallbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FlexPayCallbackController extends Controller
{
    public function __invoke(Request $request, CheckoutManager $checkout): JsonResponse
    {
        $result = $checkout->handleCallback(new HandleCheckoutCallbackRequest(
            $request->headers->all(),
            $request->getContent(),
            $request->query()
        ));
        return response()->json(['acknowledged' => true, 'checkout_id' => $result->checkout->checkoutId]);
    }
}
