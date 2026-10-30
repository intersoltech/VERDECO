<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Variance;

use App\Services\Costing\Contracts\VarianceStrategyInterface;
use App\Services\Costing\DecimalMath;

final class UtilityEfficiencyVarianceStrategy implements VarianceStrategyInterface
{
    public function key(): string
    {
        return 'utility_efficiency';
    }

    public function label(): string
    {
        return 'Energy & Utility Intensity Variance';
    }

    public function calculate(array $actual, array $standard): string
    {
        $actualKwhPerKg = DecimalMath::toDecimal($actual['kwh_per_kg'] ?? '0');
        $standardKwhPerKg = DecimalMath::toDecimal($standard['kwh_per_kg'] ?? '0');
        $outputKg = DecimalMath::toDecimal($actual['good_output_kg'] ?? '0');
        $utilityRate = DecimalMath::toDecimal($actual['utility_rate'] ?? '0');

        $kwhDiff = DecimalMath::subtract($actualKwhPerKg, $standardKwhPerKg);
        $totalPowerDiff = DecimalMath::multiply($kwhDiff, $outputKg);

        return DecimalMath::multiply($totalPowerDiff, $utilityRate);
    }
}
