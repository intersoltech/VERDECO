<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Domain\MasterData\Models\Unit;
use App\Services\Costing\DecimalMath;

final class DashboardDisplayHelper
{
    public const UNIT_KG = 'kg';

    public const UNIT_LB = 'lb';

    public static function currentUnit(): string
    {
        return (string) session('display_unit', self::UNIT_KG);
    }

    public static function setUnit(string $unit): void
    {
        $normalized = strtolower(trim($unit)) === self::UNIT_LB ? self::UNIT_LB : self::UNIT_KG;
        session(['display_unit' => $normalized]);
    }

    public static function weightLabel(?string $unit = null): string
    {
        $u = $unit ?? self::currentUnit();

        return $u === self::UNIT_LB ? 'US Pounds (lb)' : 'Kilograms (kg)';
    }

    public static function unitCostLabel(?string $unit = null): string
    {
        $u = $unit ?? self::currentUnit();

        return $u === self::UNIT_LB ? '$/lb' : '$/kg';
    }

    public static function convertWeight(float|int|string $kg, ?string $toUnit = null): float
    {
        $unit = $toUnit ?? self::currentUnit();
        if ($unit === self::UNIT_LB) {
            return (float) Unit::convert($kg, 'kg', 'lb');
        }

        return (float) $kg;
    }

    public static function convertUnitCost(float|int|string $costPerKg, ?string $toUnit = null): float
    {
        $unit = $toUnit ?? self::currentUnit();
        if ($unit === self::UNIT_LB) {
            // $/lb = $/kg * 0.45359237
            return (float) DecimalMath::multiply((string) $costPerKg, '0.45359237');
        }

        return (float) $costPerKg;
    }

    public static function formatMoney(float|int|string $amount, int $decimals = 2): string
    {
        return '$'.number_format((float) $amount, $decimals);
    }
}
