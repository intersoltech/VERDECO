<?php

declare(strict_types=1);

namespace App\Services\Costing\Strategies\Variance;

use App\Services\Costing\Contracts\VarianceStrategyInterface;
use App\Services\Costing\DecimalMath;

final class MaterialUsageVarianceStrategy implements VarianceStrategyInterface
{
    public function key(): string
    {
        return 'material_usage';
    }

    public function label(): string
    {
        return 'Material Usage / Yield Variance';
    }

    public function calculate(array $actual, array $standard): string
    {
        $actualKg = DecimalMath::toDecimal($actual['material_kg'] ?? '0');
        $standardKg = DecimalMath::toDecimal($standard['material_kg'] ?? '0');
        $standardRate = DecimalMath::toDecimal($standard['material_unit_price'] ?? '0');

        return DecimalMath::multiply(
            DecimalMath::subtract($actualKg, $standardKg),
            $standardRate
        );
    }
}
