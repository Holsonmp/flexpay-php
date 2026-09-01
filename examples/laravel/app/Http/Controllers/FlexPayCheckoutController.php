<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\InitializeFlexPayCheckoutRequest;
use App\Models\Order;
use Holduix\FlexPay\Checkout\CheckoutManager;
use Holduix\FlexPay\Checkout\CheckoutPublicView;
use Holduix\FlexPay\Checkout\DTO\CreateCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\HandleCheckoutReturnRequest;
use Holduix\FlexPay\Checkout\DTO\InitializeCheckoutRequest;
use Holduix\FlexPay\Checkout\DTO\RefreshCheckoutStatusRequest;
use Holduix\FlexPay\Checkout\Http\CheckoutResponseSerializer;
use Holduix\FlexPay\Enum\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class FlexPayCheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutManager $checkout,
        private readonly CheckoutResponseSerializer $serializer = new CheckoutResponseSerializer()
    ) {
    }

    public function create(Request $request, Order $order): JsonResponse
    {
        $this->authorize('pay', $order);
        $session = $this->checkout->create(new CreateCheckoutRequest(
            merchantReference: $order->reference,
            amount: (float) $order->total_amount,
            currency: $order->currency,
            // La carte n’est ajoutée qu’après injection d’un adaptateur hébergé confirmé.
            allowedMethods: [PaymentMethod::MOBILE_MONEY],
            successUrl: route('flexpay.result'),
            cancelUrl: route('flexpay.cancel'),
            callbackUrl: route('flexpay.callback'),
            customerName: $order->customer_name,
            customerEmail: $order->customer_email
        ));
        return response()->json($this->serializer->details(CheckoutPublicView::fromSession($session)), 201);
    }

    public function details(string $checkoutId): JsonResponse
    {
        return response()->json($this->serializer->details($this->checkout->details($checkoutId)));
    }

    public function initialize(InitializeFlexPayCheckoutRequest $request, string $checkoutId): JsonResponse
    {
        $result = $this->checkout->initialize(new InitializeCheckoutRequest(
            $checkoutId,
            PaymentMethod::from($request->string('payment_method')->toString()),
            $request->string('phone_number')->toString()
        ));
        return response()->json($this->serializer->operation($result), $result->checkout->terminal ? 200 : 202);
    }

    public function status(string $checkoutId): JsonResponse
    {
        return response()->json($this->serializer->operation(
            $this->checkout->refreshStatus(new RefreshCheckoutStatusRequest($checkoutId))
        ));
    }

    public function paymentReturn(Request $request, string $checkoutId): RedirectResponse
    {
        $result = $this->checkout->handleReturn(new HandleCheckoutReturnRequest($checkoutId, $request->query()));
        return redirect()->route('flexpay.result', ['checkout_id' => $result->checkout->checkoutId]);
    }
}
