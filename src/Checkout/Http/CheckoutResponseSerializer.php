<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Checkout\Http;

use Holduix\FlexPay\Checkout\CheckoutActionType;
use Holduix\FlexPay\Checkout\CheckoutPublicView;
use Holduix\FlexPay\Checkout\DTO\CheckoutOperationResult;
use Holduix\FlexPay\Checkout\Exception\CheckoutException;

final class CheckoutResponseSerializer
{
    /** @return array<string, mixed> */
    public function details(CheckoutPublicView $checkout): array
    {
        return $checkout->toArray();
    }

    /** @return array<string, mixed> */
    public function operation(CheckoutOperationResult $result): array
    {
        $response = $result->checkout->toArray();
        $action = ['type' => $result->action->type->value];
        if ($result->action->type === CheckoutActionType::POLL) {
            $action['poll_url'] = $result->action->url;
            $action['after_ms'] = $result->action->afterMilliseconds;
        }
        if ($result->action->type === CheckoutActionType::REDIRECT) {
            $action['redirect_url'] = $result->action->url;
        }
        $response['action'] = $action;
        $response['idempotent'] = $result->idempotent;

        return $response;
    }

    /** @return array<string, mixed> */
    public function error(CheckoutException $exception): array
    {
        return [
            'status' => 'error',
            'terminal' => false,
            'message' => $exception->publicMessage,
            'error' => [
                'code' => $exception->errorCode,
                'message' => $exception->publicMessage,
            ],
        ];
    }
}
