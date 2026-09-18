<?php

declare(strict_types=1);

namespace Modules\SAO\Drivers\Support;

/**
 * Reading values out of a third-party JSON payload.
 *
 * The helpers are static because half the call sites are inside static closures —
 * `array_map(static fn (...) => ...)` over a decoded response — where `$this` does
 * not exist. A static method is callable both ways.
 *
 * Every driver here decodes someone else's API response, so each field is `mixed`:
 * the provider promises a shape, not a type system. Casting straight to string or
 * int works until the day a field arrives as an array or an object, and `(string)`
 * on an array is a fatal — the crash lands far from the payload that caused it.
 *
 * These helpers keep the reading total: a value that is not usable as a scalar is
 * absent, which is what every call site already assumed.
 */
trait ReadsExternalPayloads
{
    /**
     * The value as a string, or null when the payload does not carry a usable one.
     */
    protected static function stringOrNull(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * The value as a string, falling back when the payload does not carry one.
     */
    protected static function stringOr(mixed $value, string $fallback = ''): string
    {
        return self::stringOrNull($value) ?? $fallback;
    }

    /**
     * The value as an int, or null when the payload does not carry a usable one.
     */
    protected static function intOrNull(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : null;
    }

    /**
     * The value as an int, falling back when the payload does not carry one.
     */
    protected static function intOr(mixed $value, int $fallback = 0): int
    {
        return self::intOrNull($value) ?? $fallback;
    }
}
