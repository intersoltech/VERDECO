<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Variance;

use App\Services\Costing\Contracts\VarianceStrategyInterface;
use App\Services\Costing\DecimalMath;

final class LaborEfficiencyVarianceStrategy implements VarianceStrategyInterface
{
    public function key(): string
    {
        return 'labor_efficiency';
    }

    public function label(): string
    {
        return 'Labor Efficiency Variance';
    }

    public function calculate(array $actual, array $standard): string
    {
        $actualHours = DecimalMath::toDecimal($actual['labor_hours'] ?? '0');
        $standardHours = DecimalMath::toDecimal($standard['labor_hours'] ?? '0');
        $standardRate = DecimalMath::toDecimal($standard['labor_rate'] ?? '0');

        return DecimalMath::multiply(
            DecimalMath::subtract($actualHours, $standardHours),
            $standardRate
        );
    }
}
