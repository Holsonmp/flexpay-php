<?php

/**
 * FlexPay Intégration By Holsonmp - Holduix
 *
 * Copyright (c) 2026 Holson Mpangala (Holsonmp) / Holduix
 *
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Holduix\FlexPay\Mapper;

use Holduix\FlexPay\Enum\NormalizedPaymentStatus;
use Holduix\FlexPay\Support\DataExtractor;

final class StatusMapper
{
    /** @var list<string> */
    private const STATUS_KEYS = [
        'transaction_status',
        'payment_status',
        'status',
        'state',
        'result',
        'response_code',
        'responseCode',
        'code',
    ];

    public function __construct(private readonly DataExtractor $extractor)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function map(array $payload): StatusMapping
    {
        foreach ($this->extractor->collect($payload, self::STATUS_KEYS) as $value) {
            if (is_bool($value) || is_float($value)) {
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }

            $raw = trim((string) $value);
            if ($raw === '') {
                continue;
            }

            $mapped = $this->mapValue($raw);
            if ($mapped !== NormalizedPaymentStatus::UNKNOWN) {
                return new StatusMapping($mapped, $raw);
            }
        }

        $raw = $this->extractor->firstScalar($payload, self::STATUS_KEYS);
        return new StatusMapping(NormalizedPaymentStatus::UNKNOWN, $raw);
    }

    public function legacyCode(StatusMapping $mapping): ?int
    {
        if ($mapping->normalizedStatus === NormalizedPaymentStatus::SUCCESSFUL) {
            return 0;
        }
        if ($mapping->normalizedStatus->isPending()) {
            return 2;
        }
        if ($mapping->normalizedStatus->isFinal()) {
            return 1;
        }

        return null;
    }

    private function mapValue(string $raw): NormalizedPaymentStatus
    {
        if (preg_match('/^[0-9]+$/', $raw) === 1) {
            return match ($raw) {
                '0' => NormalizedPaymentStatus::SUCCESSFUL,
                '1' => NormalizedPaymentStatus::FAILED,
                '2' => NormalizedPaymentStatus::PENDING,
                default => NormalizedPaymentStatus::UNKNOWN,
            };
        }

        $value = strtolower(trim(str_replace(['-', ' '], '_', $raw)));
        $failureWords = ['failed', 'failure', 'declined', 'error', 'rejected', 'invalid', 'insufficient'];
        if (in_array($value, $failureWords, true)) {
            return NormalizedPaymentStatus::FAILED;
        }
        if (in_array($value, ['cancelled', 'canceled'], true)) {
            return NormalizedPaymentStatus::CANCELLED;
        }
        if (in_array($value, ['expired', 'timeout'], true)) {
            return NormalizedPaymentStatus::EXPIRED;
        }
        if (in_array($value, ['requires_action', 'action_required'], true)) {
            return NormalizedPaymentStatus::REQUIRES_ACTION;
        }
        if (in_array($value, ['processing', 'in_progress'], true)) {
            return NormalizedPaymentStatus::PROCESSING;
        }
        if (in_array($value, ['pending', 'awaiting', 'initiated', 'request'], true)) {
            return NormalizedPaymentStatus::PENDING;
        }
        if ($value === 'created') {
            return NormalizedPaymentStatus::CREATED;
        }
        $successWords = ['success', 'successful', 'paid', 'approved', 'completed', 'complete', 'verified', 'ok'];
        if (in_array($value, $successWords, true)) {
            return NormalizedPaymentStatus::SUCCESSFUL;
        }

        return NormalizedPaymentStatus::UNKNOWN;
    }
}
