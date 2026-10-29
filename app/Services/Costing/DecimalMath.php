<?php

declare(strict_types=1);

namespace App\Services\Costing;

final class DecimalMath
{
    public static function toDecimal(mixed $value): string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? '0' : $value;
        }

        if (is_int($value) || is_float($value)) {
            return self::normalize((string) $value);
        }

        return '0';
    }

    public static function normalize(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '0';
        }

        $normalized = bcadd($value, '0', 6);

        return rtrim(rtrim($normalized, '0'), '.');
    }

    public static function add(string|int|float ...$values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, self::toDecimal($value), 6);
        }

        return self::normalize($total);
    }

    public static function subtract(string|int|float $left, string|int|float $right): string
    {
        return self::normalize(bcsub(self::toDecimal($left), self::toDecimal($right), 6));
    }

    public static function multiply(string|int|float $left, string|int|float $right): string
    {
        return self::normalize(bcmul(self::toDecimal($left), self::toDecimal($right), 6));
    }

    public static function divide(string|int|float $left, string|int|float $right): string
    {
        $left = self::toDecimal($left);
        $right = self::toDecimal($right);

        if (bccomp($right, '0', 6) === 0) {
            return '0';
        }

        return self::normalize(bcdiv($left, $right, 6));
    }

    public static function sum(array $values): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = bcadd($total, self::toDecimal($value), 6);
        }

        return self::normalize($total);
    }
}
