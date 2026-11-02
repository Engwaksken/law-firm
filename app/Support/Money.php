<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic for money (bcmath, 2 decimal places, half-up
 * rounding). Never use floats for amounts.
 */
final class Money
{
    private const SCALE = 2;

    public static function add(string|int|float|null ...$values): string
    {
        return array_reduce($values, fn (string $carry, $v) => bcadd($carry, self::norm($v), self::SCALE), '0.00');
    }

    public static function sub(string|int|float|null $a, string|int|float|null $b): string
    {
        return bcsub(self::norm($a), self::norm($b), self::SCALE);
    }

    /** Multiply then round half-up to 2 dp (e.g. quantity × unit price, amount × tax rate). */
    public static function mul(string|int|float|null $a, string|int|float|null $b): string
    {
        return self::round(bcmul(self::norm($a), self::norm($b), 6));
    }

    /** $amount × $percent / 100, rounded half-up. */
    public static function percent(string|int|float|null $amount, string|int|float|null $percent): string
    {
        return self::round(bcdiv(bcmul(self::norm($amount), self::norm($percent), 8), '100', 8));
    }

    /** Hours from minutes × hourly rate, rounded half-up. */
    public static function forMinutes(int $minutes, string|int|float|null $hourlyRate): string
    {
        return self::round(bcdiv(bcmul((string) $minutes, self::norm($hourlyRate), 8), '60', 8));
    }

    public static function cmp(string|int|float|null $a, string|int|float|null $b): int
    {
        return bccomp(self::norm($a), self::norm($b), self::SCALE);
    }

    public static function isZero(string|int|float|null $a): bool
    {
        return self::cmp($a, 0) === 0;
    }

    public static function max0(string|int|float|null $a): string
    {
        return self::cmp($a, 0) < 0 ? '0.00' : self::norm($a);
    }

    public static function format(string|int|float|null $amount, string $currency = 'UGX'): string
    {
        // UGX is quoted without decimals in practice.
        $decimals = in_array($currency, ['UGX', 'KES', 'TZS', 'RWF'], true) ? 0 : 2;

        return $currency.' '.number_format((float) self::norm($amount), $decimals);
    }

    private static function round(string $value): string
    {
        $sign = str_starts_with($value, '-') ? '-' : '';
        $half = $sign.'0.005';

        return bcadd($value, $half, self::SCALE);
    }

    private static function norm(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '0.00';
        }
        if (is_float($value)) {
            $value = number_format($value, 6, '.', '');
        }
        $value = trim((string) $value);
        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("Invalid monetary amount: {$value}");
        }

        return bcadd($value, '0', self::SCALE + 4);
    }
}
