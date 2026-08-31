<?php

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
        'message',
        'messsage',
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
            if (is_bool($value)) {
                return new StatusMapping(
                    $value ? NormalizedPaymentStatus::SUCCESSFUL : NormalizedPaymentStatus::FAILED,
                    $value ? 'true' : 'false'
                );
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
        if (is_numeric($raw)) {
            return match ((int) $raw) {
                0 => NormalizedPaymentStatus::SUCCESSFUL,
                1 => NormalizedPaymentStatus::FAILED,
                2 => NormalizedPaymentStatus::PENDING,
                default => NormalizedPaymentStatus::UNKNOWN,
            };
        }

        $value = strtolower(trim(str_replace(['-', ' '], '_', $raw)));
        $failureWords = ['failed', 'failure', 'declined', 'error', 'rejected', 'invalid', 'insufficient'];
        if ($this->contains($value, $failureWords)) {
            return NormalizedPaymentStatus::FAILED;
        }
        if ($this->contains($value, ['cancelled', 'canceled'])) {
            return NormalizedPaymentStatus::CANCELLED;
        }
        if ($this->contains($value, ['expired', 'timeout'])) {
            return NormalizedPaymentStatus::EXPIRED;
        }
        if ($this->contains($value, ['requires_action', 'action_required'])) {
            return NormalizedPaymentStatus::REQUIRES_ACTION;
        }
        if ($this->contains($value, ['processing', 'in_progress'])) {
            return NormalizedPaymentStatus::PROCESSING;
        }
        if ($this->contains($value, ['pending', 'awaiting', 'initiated', 'request'])) {
            return NormalizedPaymentStatus::PENDING;
        }
        if ($this->contains($value, ['created'])) {
            return NormalizedPaymentStatus::CREATED;
        }
        $successWords = ['success', 'successful', 'paid', 'approved', 'completed', 'complete', 'verified', 'ok'];
        if ($this->contains($value, $successWords)) {
            return NormalizedPaymentStatus::SUCCESSFUL;
        }

        return NormalizedPaymentStatus::UNKNOWN;
    }

    /**
     * @param list<string> $needles
     */
    private function contains(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
