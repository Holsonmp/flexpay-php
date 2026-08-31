<?php

declare(strict_types=1);

namespace Holduix\FlexPay\Support;

final class Redactor
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function redact(array $data): array
    {
        $redacted = [];
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if ($this->isSensitiveKey($normalizedKey)) {
                $redacted[(string) $key] = '[REDACTED]';
                continue;
            }
            if ($this->isPhoneKey($normalizedKey) && is_scalar($value)) {
                $redacted[(string) $key] = $this->maskPhone((string) $value);
                continue;
            }
            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $redacted[(string) $key] = $this->redact($value);
                continue;
            }
            if (is_string($value)) {
                $redacted[(string) $key] = $this->redactString($value);
                continue;
            }
            $redacted[(string) $key] = $value;
        }

        return $redacted;
    }

    public function maskPhone(string $phone): string
    {
        $hasPlus = str_starts_with(trim($phone), '+');
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) < 7) {
            return '[REDACTED]';
        }

        $prefixLength = $hasPlus ? 3 : 3;
        $prefix = substr($digits, 0, $prefixLength);
        $suffix = substr($digits, -3);
        $masked = $prefix . str_repeat('*', max(3, strlen($digits) - $prefixLength - 3)) . $suffix;

        return $hasPlus ? '+' . $masked : $masked;
    }

    private function isSensitiveKey(string $key): bool
    {
        if ($key === 'merchant') {
            return true;
        }

        return preg_match(
            '/(^|_)(authorization|api_?key|api_?token|token|secret|cvv|cvc|pan|card_?number'
                . '|password|merchant_?code|merchant_?id|app_?code)($|_)/',
            $key
        ) === 1;
    }

    private function isPhoneKey(string $key): bool
    {
        return str_contains($key, 'phone') || str_contains($key, 'telephone');
    }

    private function redactString(string $value): string
    {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace_callback(
            '/(?<!\d)\+?\d{10,15}(?!\d)/',
            fn (array $matches): string => $this->maskPhone($matches[0]),
            $value
        ) ?? $value;
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return $value;
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['query'])) {
            return $value;
        }

        parse_str((string) $parts['query'], $parsedQuery);
        $query = [];
        foreach ($parsedQuery as $key => $queryValue) {
            $query[(string) $key] = $queryValue;
        }
        $query = $this->redact($query);

        $rebuilt = (string) ($parts['scheme'] ?? '') . '://' . (string) ($parts['host'] ?? '');
        if (isset($parts['port'])) {
            $rebuilt .= ':' . $parts['port'];
        }
        $rebuilt .= (string) ($parts['path'] ?? '');
        $rebuilt .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $rebuilt;
    }
}
