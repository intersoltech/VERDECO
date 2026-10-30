<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Variance;

use App\Services\Costing\Contracts\VarianceStrategyInterface;
use App\Services\Costing\DecimalMath;

final class LaborRateVarianceStrategy implements VarianceStrategyInterface
{
    public function key(): string
    {
        return 'labor_rate';
    }

    public function label(): string
    {
        return 'Labor Rate Variance';
    }

    public function calculate(array $actual, array $standard): string
    {
        $actualRate = DecimalMath::toDecimal($actual['labor_rate'] ?? '0');
        $standardRate = DecimalMath::toDecimal($standard['labor_rate'] ?? '0');
        $actualHours = DecimalMath::toDecimal($actual['labor_hours'] ?? '0');

        return DecimalMath::multiply(
            DecimalMath::subtract($actualRate, $standardRate),
            $actualHours
        );
    }
}
