<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Support;

final class DataExtractor
{
    /** @var list<string> */
    private const CONTAINERS = ['data', 'result', 'response', 'transaction'];

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    public function firstScalar(array $payload, array $keys): ?string
    {
        foreach ($this->collect($payload, $keys) as $value) {
            if (is_scalar($value)) {
                $normalized = trim((string) $value);
                if ($normalized !== '') {
                    return $normalized;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     * @return list<mixed>
     */
    public function collect(array $payload, array $keys): array
    {
        $values = [];
        $this->collectFromLevel($payload, $keys, $values);

        foreach (self::CONTAINERS as $container) {
            $nested = $payload[$container] ?? null;
            if (!is_array($nested)) {
                continue;
            }
            $normalizedNested = $this->withStringKeys($nested);
            $this->collectFromLevel($normalizedNested, $keys, $values);

            $transaction = $normalizedNested['transaction'] ?? null;
            if (is_array($transaction)) {
                $this->collectFromLevel($this->withStringKeys($transaction), $keys, $values);
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     * @param list<mixed> $values
     */
    private function collectFromLevel(array $payload, array $keys, array &$values): void
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                $values[] = $payload[$key];
            }
        }
    }

    /**
     * @param array<mixed, mixed> $values
     * @return array<string, mixed>
     */
    private function withStringKeys(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }
}
